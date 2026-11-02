<?php

namespace App\Domain\Orders\Actions;

use App\Domain\Checkout\Actions\PriceCalculator;
use App\Domain\Orders\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class EditOrderItems
{
    /** @param array<int,int> $quantities */
    public function execute(Order $order, array $quantities): void
    {
        app(OrderAccess::class)->staff();
        DB::transaction(function () use ($order, $quantities) {
            $o = Order::lockForUpdate()->findOrFail($order->id);
            if ($o->shipments()->exists() || $o->paid_amount || ! in_array($o->status, ['pending', 'confirmed', 'processing', 'packed', 'on-hold'])) {
                throw ValidationException::withMessages(['order' => __('orders.invalid_operation')]);
            }
            $wasDeducted = $o->items()->where('stock_deducted', '>', 0)->exists();
            $lines = [];
            $items = $o->items()->orderBy('id')->get();
            foreach ($items as $i) {
                if ($i->tax && ! $i->tax_bps) {
                    throw ValidationException::withMessages(['order' => __('orders.legacy_tax_edit')]);
                }
                $qty = $quantities[$i->id] ?? $i->qty;
                if ($qty < 1 || $qty > 100) {
                    throw ValidationException::withMessages(['order' => __('orders.invalid_quantity')]);
                }
                if ($i->stock_deducted) {
                    app(OrderInventory::class)->restock($i, $i->stock_deducted - $i->restocked_qty);
                    $i->update(['stock_deducted' => 0, 'restocked_qty' => 0]);
                }
                $lines[] = ['id' => $i->id, 'subtotal' => intdiv(($i->pricing_subtotal ?? $i->subtotal) * $qty + intdiv($i->pricing_qty ?? $i->qty, 2), $i->pricing_qty ?? $i->qty), 'qty' => $qty, 'tax_bps' => $i->tax_bps, 'tax_inclusive' => $i->tax_inclusive];
            }
            if (array_sum(array_column($lines, 'subtotal')) > 100000000000) {
                throw ValidationException::withMessages(['order' => __('checkout.amount_limit')]);
            }
            $giftTax = $o->tax - $o->items->sum('tax');
            $totals = app(PriceCalculator::class)->calculate($lines, $o->original_discount ?? $o->discount, $o->shipping, $o->gift_fee);
            foreach ($totals['lines'] as $line) {
                $o->items()->whereKey($line['id'])->update(array_intersect_key($line, array_flip(['qty', 'subtotal', 'discount', 'tax', 'total'])));
            }
            $o->update(array_intersect_key($totals, array_flip(['subtotal', 'discount', 'tax', 'tax_included', 'total'])));
            $o->increment('tax', $giftTax);
            $o->increment('total', $giftTax);
            $o->update(['display_total' => (int) bcdiv(bcmul((string) $o->total, (string) $o->display_rate_ppm, 0), '1000000', 0)]);
            if ($o->stock_policy === 'placement' || $wasDeducted) {
                app(OrderInventory::class)->deduct($o);
            }
            app(ManageOrder::class)->event($o, 'edited', __('orders.items_edited'), true);
        }, 3);
    }
}
