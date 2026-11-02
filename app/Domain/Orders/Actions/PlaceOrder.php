<?php

namespace App\Domain\Orders\Actions;

use App\Domain\Cart\Models\Cart;
use App\Domain\Checkout\Actions\CheckoutQuote;
use App\Domain\Customers\Actions\CreateCheckoutAccount;
use App\Domain\Customers\Actions\NormalizePhone;
use App\Domain\Marketing\Actions\ValidateCoupon;
use App\Domain\Orders\Models\Order;
use App\Domain\Settings\Actions\CurrencyDisplay;
use App\Domain\Shipping\Models\Location;
use App\Jobs\SendOrderConfirmation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PlaceOrder
{
    /** @param array<string,mixed> $data */
    public function execute(Cart $cart, array $data): Order
    {
        $data['phone'] = app(NormalizePhone::class)->execute($data['phone']);

        return DB::transaction(function () use ($cart, $data): Order {
            $cart = Cart::query()->lockForUpdate()->findOrFail($cart->id);
            $existing = Order::where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                abort_unless($existing->cart_id === $cart->id, 403);

                return $existing;
            }
            if (! Auth::guard('web')->check() && ! store_setting('checkout_guest_enabled', config('checkout.guest_enabled'))) {
                throw ValidationException::withMessages(['cart' => __('checkout.guest_disabled')]);
            }
            $division = Location::where('type', 'division')->where('country', $data['country'])->findOrFail($data['division_id']);
            $district = Location::where('type', 'district')->where('parent_id', $division->id)->find($data['district_id']);
            $upazila = $district ? Location::where('type', 'upazila')->where('parent_id', $district->id)->find($data['upazila_id']) : null;
            if (! $district || ! $upazila) {
                throw ValidationException::withMessages(['district_id' => __('checkout.invalid_address')]);
            }
            $areas = Location::where('type', 'area')->where('parent_id', $upazila->id);
            $area = null;
            if (! empty($data['area_id'])) {
                $area = (clone $areas)->find($data['area_id']);
            } if ((! empty($data['area_id']) && ! $area) || ($areas->exists() && ! $area)) {
                throw ValidationException::withMessages(['area_id' => __('checkout.invalid_address')]);
            }
            $methods = store_setting('checkout_payment_methods', config('checkout.payment_methods'));
            if (empty($methods[$data['payment_method']]['enabled']) || ($data['payment_method'] !== 'cod' && empty($methods[$data['payment_method']]['instructions']))) {
                throw ValidationException::withMessages(['payment_method' => __('checkout.payment_unavailable')]);
            }
            if ($data['payment_method'] !== 'cod' && empty($data['payment_reference']) && empty($data['payment_proof'])) {
                throw ValidationException::withMessages(['payment_reference' => __('checkout.payment_reference_required')]);
            }
            $quote = app(CheckoutQuote::class)->execute($cart, $data, true);
            foreach ($quote['lines'] as $line) {
                $product = $line['product'];
                $variant = $line['variant'];
                if ($variant) {
                    $variant->decrement('qty', $line['qty']);
                } elseif ($product->manage_stock && $product->stock_status !== 'backorder') {
                    $product->decrement('qty', $line['qty']);
                }
            }
            $user = app(CreateCheckoutAccount::class)->execute($data);
            $display = app(CurrencyDisplay::class)->current();
            $order = Order::create(['number' => app(OrderNumber::class)->generate(), 'idempotency_key' => $data['idempotency_key'], 'cart_id' => $cart->id, 'user_id' => $user?->id, 'phone' => $data['phone'], 'guest_email' => $data['email'] ?? null, 'currency' => config('checkout.base_currency'), 'display_currency' => $display->code ?? config('checkout.base_currency'), 'display_rate_ppm' => $display ? app(CurrencyDisplay::class)->rate($display) : 1000000, 'display_total' => $display ? app(CurrencyDisplay::class)->convert($quote['total'], $display) : $quote['total'], 'subtotal' => $quote['subtotal'], 'discount' => $quote['discount'], 'shipping' => $quote['shipping'], 'tax' => $quote['tax'], 'tax_included' => $quote['tax_included'], 'gift_fee' => $quote['gift_fee'], 'total' => $quote['total'], 'is_gift' => $data['is_gift'] ?? false, 'gift_message' => ! empty($data['is_gift']) ? ($data['gift_message'] ?? null) : null, 'hide_prices' => ! empty($data['is_gift']) && ! empty($data['hide_prices']), 'recipient_name' => ! empty($data['is_gift']) ? ($data['recipient_name'] ?? null) : null, 'recipient_phone' => ! empty($data['is_gift']) && ! empty($data['recipient_phone']) ? app(NormalizePhone::class)->execute($data['recipient_phone']) : null, 'shipping_address' => ['name' => $data['name'], 'country' => $data['country'], 'division' => $division->name, 'district' => $district->name, 'upazila' => $upazila->name, 'area' => $area->name ?? ($data['area'] ?? null), 'line1' => $data['address']], 'note' => $data['note'] ?? null, 'payment_method' => $data['payment_method'], 'payment_reference' => $data['payment_reference'] ?? null, 'payment_proof' => $data['payment_proof'] ?? null, 'shipping_method' => $quote['shipping_method'], 'coupon_id' => $quote['coupon']?->id, 'placed_at' => now()]);
            foreach ($quote['lines'] as $line) {
                $order->items()->create(array_intersect_key($line, array_flip(['product_id', 'variant_id', 'name', 'sku', 'qty', 'unit_price', 'subtotal', 'discount', 'tax', 'tax_inclusive', 'total', 'options'])));
            }
            if ($quote['coupon']) {
                $coupon = $quote['coupon'];
                $coupon->increment('used');
                DB::table('coupon_usages')->insert(['coupon_id' => $coupon->id, 'order_id' => $order->id, 'identity_hash' => app(ValidateCoupon::class)->identity($data['phone']), 'created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('order_events')->insert(['order_id' => $order->id, 'type' => 'placed', 'note' => __('checkout.order_placed'), 'is_public' => true, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('order_notifications')->insert(['order_id' => $order->id, 'created_at' => now(), 'updated_at' => now()]);
            $cart->items()->where('saved', false)->delete();
            $cart->update(['coupon_id' => null]);
            DB::afterCommit(fn () => SendOrderConfirmation::dispatch($order->id));

            return $order;
        }, 3);
    }
}
