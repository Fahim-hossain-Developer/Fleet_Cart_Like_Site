<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Orders\Actions\ManageOrder;
use App\Domain\Orders\Actions\OrderInventory;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderRefund;

final class RecordRefundReceipt
{
    /**
     * @param array<int,int> $restock */
    public function execute(Order $o, int $amount, string $reason, string $key, ?int $actorId, array $restock = [], string $gateway = 'manual', ?string $providerReference = null, ?int $except = null): OrderRefund
    {
        $old = OrderRefund::where('key', $key)->first();
        if ($old) {
            abort_unless($old->order_id === $o->id, 403);

            return $old;
        }
        $r = app(RefundRules::class)->validate($o, $amount, $restock, $except);
        $shipped = $o->shipments()->exists();
        foreach ($restock as $id => $qty) {
            app(OrderInventory::class)->restock($o->items()->findOrFail($id), $qty);
        }
        $receipt = $o->refunds()->create(['key' => $key, 'amount' => $amount, 'reason' => $reason, 'items' => $restock, 'admin_id' => $actorId, 'gateway' => $gateway, 'provider_reference' => $providerReference]);
        $refunded = $o->refunded_amount + $amount;
        $o->update(['refunded_amount' => $refunded, 'payment_status' => $refunded === $o->paid_amount ? 'refunded' : 'partial_refund']);
        if (! $shipped && $refunded === $o->total) {
            $o->update(['status' => 'refunded']);
        }
        if ($shipped && $r) {
            $cap = 0;
            foreach ($r->items as $id => $qty) {
                $i = $o->items()->findOrFail($id);
                $cap += intdiv($i->total * $qty, $i->qty);
            }if ($refunded === $cap) {
                $r->update(['status' => 'refunded']);
                $o->update(['status' => 'returned']);
            }
        }
        app(ManageOrder::class)->event($o, 'refund', $gateway === 'manual' ? __('orders.manual_refund_recorded') : __('payments.provider_refund_verified'), true);

        return $receipt;
    }
}
