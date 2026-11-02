<?php

namespace App\Domain\Marketing\Actions;

use App\Domain\Marketing\Models\FlashAllocation;
use App\Domain\Marketing\Models\FlashSaleItem;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderItem;
use Illuminate\Validation\ValidationException;

final class FlashInventory
{
    // Caller holds the order and relevant product/flash-row locks.
    public function allocate(OrderItem $item, int $flashId): void
    {
        $f = FlashSaleItem::lockForUpdate()->findOrFail($flashId);
        if ($f->sold + $item->qty > $f->quota) {
            throw ValidationException::withMessages(['cart' => __('marketing.flash_exhausted')]);
        }
        $f->increment('sold', $item->qty);
        FlashAllocation::create(['order_item_id' => $item->id, 'flash_sale_item_id' => $f->id, 'qty' => $item->qty]);
    }

    public function release(Order $order): void
    {
        $allocations = FlashAllocation::whereIn('order_item_id', $order->items()->pluck('id'))->orderBy('flash_sale_item_id')->lockForUpdate()->get();
        foreach ($allocations as $a) {
            $qty = $a->qty - $a->released;
            if ($qty <= 0) {
                continue;
            }$f = FlashSaleItem::lockForUpdate()->findOrFail($a->flash_sale_item_id);
            $f->decrement('sold', $qty);
            $a->update(['released' => $a->qty]);
        }
    }
}
