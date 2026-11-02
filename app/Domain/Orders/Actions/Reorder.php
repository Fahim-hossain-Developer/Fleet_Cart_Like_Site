<?php

namespace App\Domain\Orders\Actions;

use App\Domain\Cart\Actions\CartManager;
use App\Domain\Cart\Actions\CartSession;
use App\Domain\Orders\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class Reorder
{
    public function execute(Order $order): void
    {
        app(OrderAccess::class)->owner($order);
        $cart = app(CartSession::class)->current();
        DB::transaction(function () use ($order, $cart) {
            foreach ($order->items as $item) {
                foreach ($item->options['values'] ?? [] as $value) {
                    if (is_array($value) && isset($value['path'])) {
                        throw ValidationException::withMessages(['order' => __('orders.reselect_files')]);
                    }
                }app(CartManager::class)->add($cart, $item->product_id, $item->variant_id, $item->qty, $item->options['values'] ?? []);
            }
        }, 3);
    }
}
