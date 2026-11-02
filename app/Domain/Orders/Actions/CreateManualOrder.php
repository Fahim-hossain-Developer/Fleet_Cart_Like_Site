<?php

namespace App\Domain\Orders\Actions;

use App\Domain\Cart\Actions\CartManager;
use App\Domain\Cart\Models\Cart;
use App\Domain\Orders\Models\Order;
use Illuminate\Support\Facades\DB;

final class CreateManualOrder
{
    /** @param array<string,mixed> $data */
    public function execute(array $data): Order
    {
        app(OrderAccess::class)->staff();
        validator($data, ['idempotency_key' => 'required|uuid', 'items' => 'required|array|min:1|max:50', 'items.*.product_id' => 'required|integer', 'items.*.variant_id' => 'nullable|integer', 'items.*.qty' => 'required|integer|min:1|max:100', 'name' => 'required|string|max:100', 'phone' => 'required|string|max:32', 'email' => 'nullable|email:rfc|max:254', 'address' => 'required|string|min:5|max:500', 'country' => 'required|in:BD', 'division_id' => 'required|integer', 'district_id' => 'required|integer', 'upazila_id' => 'required|integer', 'area_id' => 'nullable|integer', 'shipping_method_id' => 'required|integer'])->validate();

        return DB::transaction(function () use ($data) {
            $key = $data['idempotency_key'];
            $token = hash('sha256', 'manual:'.$key);
            Cart::upsert([['token' => $token]], ['token'], ['updated_at']);
            $cart = Cart::where('token', $token)->lockForUpdate()->firstOrFail();
            $old = Order::where('idempotency_key', $key)->lockForUpdate()->first();
            if ($old) {
                return $old;
            }
            foreach ($data['items'] as $item) {
                app(CartManager::class)->add($cart, (int) $item['product_id'], ! empty($item['variant_id']) ? (int) $item['variant_id'] : null, (int) $item['qty']);
            }
            // Staff creation must not inherit a simultaneously logged-in storefront identity.
            $order = app(PlaceOrder::class)->execute($cart, array_replace($data, ['create_account' => false, 'is_gift' => false, 'hide_prices' => false]), true);
            app(ManageOrder::class)->event($order, 'manual', __('orders.manual_order'), false);

            return $order;
        }, 3);
    }
}
