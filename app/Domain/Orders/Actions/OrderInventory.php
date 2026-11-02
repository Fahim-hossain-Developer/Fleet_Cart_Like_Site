<?php

namespace App\Domain\Orders\Actions;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderItem;
use Illuminate\Validation\ValidationException;

final class OrderInventory
{
    // Caller holds the order lock. Stable item/product order and retryable transactions prevent double release.
    public function deduct(Order $order): void
    {
        foreach ($order->items()->orderBy('product_id')->orderBy('variant_id')->orderBy('id')->get() as $item) {
            $p = Product::withTrashed()->lockForUpdate()->findOrFail($item->product_id);
            $v = $item->variant_id ? ProductVariant::lockForUpdate()->findOrFail($item->variant_id) : null;
            if (! $v && (! $p->manage_stock || $p->stock_status === 'backorder')) {
                continue;
            }
            $needed = $item->qty - $item->stock_deducted;
            if ($needed <= 0) {
                continue;
            }
            $stock = $v ?? $p;
            if ($stock->qty < $needed) {
                throw ValidationException::withMessages(['order' => __('orders.stock_unavailable')]);
            }
            $stock->decrement('qty', $needed);
            $item->increment('stock_deducted', $needed);
        }
    }

    public function restock(OrderItem $item, int $qty): void
    {
        if ($qty < 0 || $qty > $item->stock_deducted - $item->restocked_qty) {
            throw ValidationException::withMessages(['order' => __('orders.invalid_quantity')]);
        }
        if (! $qty) {
            return;
        }
        $p = Product::withTrashed()->lockForUpdate()->findOrFail($item->product_id);
        $v = $item->variant_id ? ProductVariant::lockForUpdate()->findOrFail($item->variant_id) : null;
        ($v ?? $p)->increment('qty', $qty);
        $item->increment('restocked_qty', $qty);
    }
}
