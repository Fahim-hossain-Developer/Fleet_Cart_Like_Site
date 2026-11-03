<?php

namespace App\Domain\Checkout\Landing;

use App\Domain\Cart\Actions\QuoteCart;
use App\Domain\Cart\Models\Cart;
use App\Domain\Checkout\Actions\CheckoutQuote;
use App\Domain\Checkout\Models\LandingOrderClaim;
use App\Domain\Content\Models\LandingPage;
use App\Domain\Customers\Actions\NormalizePhone;
use App\Domain\Marketing\Actions\AutomaticDiscounts;
use App\Domain\Orders\Actions\PlaceOrder;
use App\Domain\Orders\Models\Order;
use App\Domain\Shipping\Actions\ShippingResolver;
use App\Http\Requests\CheckoutRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class LandingCheckout
{
    /** @param array<string,mixed> $input
     * @return array<string,mixed> */
    public function validate(LandingPage $page, array $input, bool $placing = true): array
    {
        $rules = (new CheckoutRequest)->rules();
        $rules += ['package_id' => 'required|integer', 'units' => 'required|integer|min:1|max:10', 'bump' => 'sometimes|boolean', 'options' => 'sometimes|array', 'quote_hash' => 'nullable|string|size:64', 'alternate_phone' => 'nullable|string|max:32', 'delivery_note' => 'nullable|string|max:500', 'preferred_time' => 'nullable|date_format:H:i', 'custom_field' => 'nullable|string|max:200'];
        if (($page->form_config['custom_type'] ?? 'text') === 'dropdown') {
            $rules['custom_field'] = ['nullable', 'string', Rule::in($page->form_config['custom_choices'] ?? [])];
        }
        if (! $placing) {
            foreach (['name', 'phone', 'division_id', 'upazila_id', 'address', 'idempotency_key'] as $key) {
                $rules[$key] = str_replace('required', 'nullable', $rules[$key]);
            }unset($rules['terms']);
        }
        $d = validator($input, $rules)->validate();
        if (! empty($d['phone'])) {
            $d['phone'] = app(NormalizePhone::class)->execute($d['phone']);
        }
        $cfg = $page->form_config;
        if (empty($cfg['email'])) {
            $d['email'] = null;
        }
        foreach (['alternate_phone', 'delivery_note', 'preferred_time'] as $field) {
            if (empty($cfg[$field])) {
                unset($d[$field]);
            }
        }
        if (empty($cfg['custom_label'])) {
            unset($d['custom_field']);
        }
        if (! empty($d['alternate_phone'])) {
            $d['alternate_phone'] = app(NormalizePhone::class)->execute($d['alternate_phone']);
        }

        return $d;
    }

    /** @param array<string,mixed> $quote */
    public function hash(Cart $cart, LandingPage $page, array $quote): string
    {
        $lines = array_map(fn ($line) => array_intersect_key($line, array_flip(['product_id', 'variant_id', 'qty', 'subtotal', 'discount', 'tax', 'total', 'flash_sale_item_id', 'package_snapshot'])), $quote['lines']);

        return hash_hmac('sha256', json_encode([$cart->token, $page->id, $lines, $quote['shipping'], $quote['gift_fee'], $quote['tax'], $quote['total']], JSON_THROW_ON_ERROR), config('app.key'));
    }

    /** @param array<string,mixed> $input
     * @return array<int,array<string,mixed>> */
    public function methods(LandingPage $page, array $input): array
    {
        $d = validator($input, ['district_id' => 'required|integer|exists:locations,id', 'package_id' => 'required|integer', 'units' => 'required|integer|min:1|max:10', 'bump' => 'sometimes|boolean', 'options' => 'sometimes|array|max:100'])->validate();
        $cart = app(LandingCart::class)->current($page);
        app(LandingCart::class)->prepare($page, $cart, $d['package_id'], $d['units'], ! empty($d['bump']), $d['options'] ?? []);
        $lines = app(QuoteCart::class)->execute($cart->refresh());
        if (collect($lines)->every(fn ($line) => $line['digital'])) {
            return [['id' => 0, 'name' => __('checkout.digital_delivery'), 'amount' => 0]];
        }
        $subtotal = array_sum(array_column($lines, 'subtotal'));
        $discount = app(AutomaticDiscounts::class)->calculate($subtotal, array_sum(array_column($lines, 'qty')));

        return array_values(app(ShippingResolver::class)->methods('BD', (int) $d['district_id'], $subtotal - $discount['discount'], array_sum(array_column($lines, 'weight')), $discount['free_shipping'] || collect($lines)->every(fn ($line) => $line['free_shipping'])));
    }

    /** @param array<string,mixed> $input
     * @return array<string,mixed> */
    public function quote(LandingPage $page, array $input): array
    {
        $d = $this->validate($page, $input, false);
        $cart = app(LandingCart::class)->current($page);

        return DB::transaction(function () use ($page, $cart, $d) {
            app(LandingCart::class)->prepare($page, $cart, $d['package_id'], $d['units'], ! empty($d['bump']), $d['options'] ?? []);
            $quote = app(CheckoutQuote::class)->execute($cart->refresh(), $d);

            return array_intersect_key($quote, array_flip(['subtotal', 'discount', 'shipping', 'gift_fee', 'tax', 'tax_included', 'total'])) + ['quote_hash' => $this->hash($cart, $page, $quote)];
        }, 3);
    }

    /** @param array<string,mixed> $input */
    public function place(LandingPage $page, array $input): Order
    {
        $d = $this->validate($page, $input);
        if (($d['payment_proof'] ?? null) instanceof UploadedFile) {
            $d['payment_proof'] = $d['payment_proof']->store('payment-proofs', 'local');
        }$cart = app(LandingCart::class)->current($page);

        return DB::transaction(function () use ($page, $d, $cart) {
            Cart::lockForUpdate()->findOrFail($cart->id);
            $old = Order::where('idempotency_key', $d['idempotency_key'])->first();
            if ($old) {
                abort_unless($old->cart_id === $cart->id && $old->landing_page_id === $page->id, 403);

                return $old;
            }
            $page = LandingPage::published()->lockForUpdate()->findOrFail($page->id);
            app(LandingCart::class)->prepare($page, $cart, $d['package_id'], $d['units'], ! empty($d['bump']), $d['options'] ?? []);
            $quote = app(CheckoutQuote::class)->execute($cart->refresh(), $d, true);
            if (empty($d['quote_hash']) || ! hash_equals($this->hash($cart, $page, $quote), $d['quote_hash'])) {
                throw ValidationException::withMessages(['quote' => __('landing.quote_changed')]);
            }
            $identity = hash_hmac('sha256', $d['phone'].'|'.$page->product_id, config('app.key'));
            LandingOrderClaim::upsert([['identity_hash' => $identity, 'created_at' => now(), 'updated_at' => now()]], ['identity_hash'], ['updated_at']);
            $claim = LandingOrderClaim::where('identity_hash', $identity)->lockForUpdate()->firstOrFail();
            if ($claim->order_id && $claim->expires_at && $claim->expires_at->isFuture()) {
                throw ValidationException::withMessages(['phone' => __('landing.duplicate_order')]);
            }
            if ($d['payment_method'] === 'cod' && ! empty($page->form_config['phone_otp'])) {
                app(CheckoutPhoneProof::class)->consume($cart, $d['phone']);
            }
            $order = app(PlaceOrder::class)->execute($cart, $d);
            $meta = array_intersect_key($d, array_flip(['alternate_phone', 'delivery_note', 'preferred_time', 'custom_field', 'country', 'district_id', 'upazila_id']));
            if (isset($meta['custom_field'])) {
                $meta['custom_label'] = $page->form_config['custom_label'] ?? [];
            }
            $order->update(['landing_page_id' => $page->id, 'landing_snapshot' => ['slug' => $page->slug, 'title' => $page->getTranslations('title'), 'product_id' => $page->product_id, 'form_config' => array_intersect_key($page->form_config, array_flip(['mode', 'custom_label', 'upsell_package_id', 'downsell_package_id', 'whatsapp']))], 'checkout_meta' => $meta]);
            $claim->update(['order_id' => $order->id, 'expires_at' => now()->addMinutes(10)]);
            $page->increment('orders_count');

            return $order;
        }, 3);
    }
}
