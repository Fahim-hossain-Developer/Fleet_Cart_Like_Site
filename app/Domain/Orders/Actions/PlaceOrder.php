<?php

namespace App\Domain\Orders\Actions;

use App\Domain\Cart\Models\Cart;
use App\Domain\Catalog\Models\ProductDownload;
use App\Domain\Checkout\Actions\CheckoutQuote;
use App\Domain\Customers\Actions\CreateCheckoutAccount;
use App\Domain\Customers\Actions\NormalizePhone;
use App\Domain\Customers\Actions\PublishNotice;
use App\Domain\Marketing\Actions\FlashInventory;
use App\Domain\Marketing\Actions\ValidateCoupon;
use App\Domain\Orders\Models\DownloadEntitlement;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Actions\AdvanceRequired;
use App\Domain\Payments\Actions\GatewayManager;
use App\Domain\Payments\Actions\PaymentMethods;
use App\Domain\Settings\Actions\CurrencyDisplay;
use App\Domain\Shipping\Models\Location;
use App\Jobs\SendOrderConfirmation;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class PlaceOrder
{
    /** @param array<string,mixed> $data */
    public function execute(Cart $cart, array $data, bool $manual = false): Order
    {
        if ($manual) {
            app(OrderAccess::class)->staff();
        }
        if (! empty($data['email'])) {
            $data['email'] = strtolower(trim($data['email']));
        }
        $data['phone'] = app(NormalizePhone::class)->execute($data['phone']);

        return DB::transaction(function () use ($cart, $data, $manual): Order {
            $cart = Cart::query()->lockForUpdate()->findOrFail($cart->id);
            $existing = Order::where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                abort_unless($existing->cart_id === $cart->id, 403);

                return $existing;
            }
            if (! $manual && ! Auth::guard('web')->check() && ! store_setting('checkout_guest_enabled', config('checkout.guest_enabled'))) {
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
            $methods = app(PaymentMethods::class)->current();
            if (empty($methods[$data['payment_method']]['enabled']) || ($data['payment_method'] !== 'cod' && empty($methods[$data['payment_method']]['instructions']))) {
                throw ValidationException::withMessages(['payment_method' => __('checkout.payment_unavailable')]);
            }
            if (in_array($data['payment_method'], ['bank', 'manual_mobile']) && empty($data['payment_reference']) && empty($data['payment_proof'])) {
                throw ValidationException::withMessages(['payment_reference' => __('checkout.payment_reference_required')]);
            }
            $quote = app(CheckoutQuote::class)->execute($cart, $data, true);
            if (! in_array($data['payment_method'], ['cod', 'bank', 'manual_mobile']) && ! isset(app(GatewayManager::class)->available(config('checkout.base_currency'), $quote['total'])[$data['payment_method']])) {
                throw ValidationException::withMessages(['payment_method' => __('checkout.payment_unavailable')]);
            }
            $user = $manual ? (! empty($data['user_id']) ? User::where('is_blocked', false)->findOrFail($data['user_id']) : null) : app(CreateCheckoutAccount::class)->execute($data);
            $display = app(CurrencyDisplay::class)->current();
            $order = Order::create(['payment_status' => $quote['total'] === 0 ? 'paid' : 'unpaid', 'stock_policy' => store_setting('orders_stock_policy', 'placement'), 'ip_hash' => request()->ip() ? hash_hmac('sha256', request()->ip(), config('app.key')) : null, 'number' => app(OrderNumber::class)->generate(), 'idempotency_key' => $data['idempotency_key'], 'cart_id' => $cart->id, 'user_id' => $user?->id, 'phone' => $data['phone'], 'guest_email' => $data['email'] ?? null, 'currency' => config('checkout.base_currency'), 'display_currency' => $display->code ?? config('checkout.base_currency'), 'display_rate_ppm' => $display ? app(CurrencyDisplay::class)->rate($display) : 1000000, 'display_total' => $display ? app(CurrencyDisplay::class)->convert($quote['total'], $display) : $quote['total'], 'advance_required' => app(AdvanceRequired::class)->calculate($quote['total'], $quote['shipping'], $data['payment_method']), 'automatic_discount' => $quote['automatic_discount'], 'coupon_discount' => $quote['coupon_discount'], 'cart_rule_snapshot' => $quote['cart_rule_snapshot'], 'original_discount' => $quote['discount'], 'subtotal' => $quote['subtotal'], 'discount' => $quote['discount'], 'shipping' => $quote['shipping'], 'tax' => $quote['tax'], 'tax_included' => $quote['tax_included'], 'gift_fee' => $quote['gift_fee'], 'total' => $quote['total'], 'is_gift' => $data['is_gift'] ?? false, 'gift_message' => ! empty($data['is_gift']) ? ($data['gift_message'] ?? null) : null, 'hide_prices' => ! empty($data['is_gift']) && ! empty($data['hide_prices']), 'recipient_name' => ! empty($data['is_gift']) ? ($data['recipient_name'] ?? null) : null, 'recipient_phone' => ! empty($data['is_gift']) && ! empty($data['recipient_phone']) ? app(NormalizePhone::class)->execute($data['recipient_phone']) : null, 'shipping_address' => ['name' => $data['name'], 'country' => $data['country'], 'division' => $division->name, 'district' => $district->name, 'upazila' => $upazila->name, 'area' => $area->name ?? ($data['area'] ?? null), 'line1' => $data['address']], 'note' => $data['note'] ?? null, 'payment_method' => $data['payment_method'], 'payment_reference' => $data['payment_reference'] ?? null, 'payment_proof' => $data['payment_proof'] ?? null, 'shipping_method' => $quote['shipping_method'], 'coupon_id' => $quote['coupon']?->id, 'placed_at' => now()]);
            foreach ($quote['lines'] as $line) {
                $item = $order->items()->create(array_intersect_key($line, array_flip(['product_id', 'variant_id', 'name', 'sku', 'qty', 'unit_price', 'subtotal', 'discount', 'tax', 'tax_inclusive', 'tax_bps', 'total', 'options'])));
                if (! empty($line['flash_sale_item_id'])) {
                    app(FlashInventory::class)->allocate($item, $line['flash_sale_item_id']);
                }
            }
            foreach ($order->items as $i) {
                $i->update(['pricing_qty' => $i->qty, 'pricing_subtotal' => $i->subtotal]);
            }
            if ($order->stock_policy === 'placement' || ($order->stock_policy === 'payment' && $order->total === 0)) {
                app(OrderInventory::class)->deduct($order);
            }
            foreach ($order->items as $item) {
                foreach (ProductDownload::where('product_id', $item->product_id)->get() as $download) {
                    DownloadEntitlement::create(['order_item_id' => $item->id, 'product_download_id' => $download->id, 'label' => $download->label ?? $item->name, 'file' => $download->file, 'external_url' => $download->external_url, 'enabled_at' => $order->total === 0 ? now() : null, 'expires_at' => $order->total === 0 && $download->expiry_days ? now()->addDays($download->expiry_days) : null, 'limit' => $download->limit, 'expiry_days' => $download->expiry_days]);
                }
            }
            $order->update(['risk_flags' => app(AssessOrderRisk::class)->flags($order)]);
            if ($quote['coupon']) {
                $coupon = $quote['coupon'];
                $coupon->increment('used');
                DB::table('coupon_usages')->insert(['coupon_id' => $coupon->id, 'order_id' => $order->id, 'identity_hash' => app(ValidateCoupon::class)->identity($data['phone']), 'created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('order_events')->insert(['order_id' => $order->id, 'type' => 'placed', 'note' => __('checkout.order_placed'), 'is_public' => true, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('order_notifications')->insert(['order_id' => $order->id, 'created_at' => now(), 'updated_at' => now()]);
            $cart->items()->where('saved', false)->delete();
            $cart->update(['coupon_id' => null]);
            DB::afterCommit(function () use ($order) {
                try {
                    SendOrderConfirmation::dispatch($order->id);
                } catch (\Throwable $e) {
                    Log::warning('Order confirmation queued for retry', ['order_id' => $order->id]);
                }
            });
            DB::afterCommit(function () use ($order) {
                try {
                    $owner = $order->user_id ? User::find($order->user_id) : null;
                    if ($owner) {
                        app(PublishNotice::class)->execute($owner, 'orders', 'marketing.notice_order', ['number' => $order->number], route('account.order', $order->number, false), 'order-created:'.$order->id, true, ['sms']);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Order account notice pending', ['order_id' => $order->id]);
                }
            });

            return $order;
        }, 3);
    }
}
