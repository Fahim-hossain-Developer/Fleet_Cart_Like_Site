<?php

namespace App\Domain\Checkout\Actions;

use App\Domain\Cart\Actions\QuoteCart;
use App\Domain\Cart\Models\Cart;
use App\Domain\Checkout\Models\TaxRate;
use App\Domain\Marketing\Actions\ValidateCoupon;
use App\Domain\Marketing\Models\Coupon;
use App\Domain\Shipping\Actions\ShippingResolver;
use Illuminate\Validation\ValidationException;

final class CheckoutQuote
{
    /** @param array<string,mixed> $data
     * @return array<string,mixed> */
    public function execute(Cart $cart, array $data, bool $lock = false): array
    {
        $lines = app(QuoteCart::class)->execute($cart, $lock);
        if (! $lines) {
            throw ValidationException::withMessages(['cart' => __('checkout.empty_cart')]);
        }
        $subtotal = array_sum(array_column($lines, 'subtotal'));
        $coupon = $cart->coupon_id ? Coupon::query()->when($lock, fn ($q) => $q->lockForUpdate())->find($cart->coupon_id) : null;
        $promotion = app(ValidateCoupon::class)->execute($coupon, $subtotal, $data['phone'] ?? null);
        $digital = collect($lines)->every(fn ($line) => $line['digital']);
        $free = $promotion['free_shipping'] || collect($lines)->every(fn ($line) => $line['free_shipping']);
        $shipping = $digital ? ['id' => 0, 'name' => __('checkout.digital_delivery'), 'amount' => 0] : app(ShippingResolver::class)->resolve($data['country'], (int) $data['district_id'], (int) $data['shipping_method_id'], $subtotal - $promotion['discount'], array_sum(array_column($lines, 'weight')), $free);
        foreach ($lines as &$line) {
            $rate = TaxRate::where('tax_class_id', $line['tax_class_id'])->where('country', $data['country'])->where('is_active', true)->where(fn ($q) => $q->whereNull('district_id')->orWhere('district_id', (int) $data['district_id']))->orderByDesc('district_id')->first();
            $line['tax_bps'] = $rate->rate_bps ?? 0;
            $line['tax_inclusive'] = $rate->inclusive ?? false;
        }unset($line);
        $giftFee = 0;
        $giftTax = 0;
        if ($data['is_gift'] ?? false) {
            if (! store_setting('checkout_gift_enabled', config('checkout.gift_enabled'))) {
                throw ValidationException::withMessages(['is_gift' => __('checkout.gift_unavailable')]);
            }
            $fees = [];
            foreach ($lines as $line) {
                $product = $line['product'];
                $fee = $product->gift_fee ?? $product->categories()->whereNotNull('gift_fee')->max('gift_fee');
                if ($fee !== null) {
                    $fees[] = (int) $fee * $line['qty'];
                }
            }
            $giftFee = $fees ? array_sum($fees) : (int) store_setting('checkout_gift_fee', config('checkout.gift_fee'));
            if ($giftFee > 100000000000) {
                throw ValidationException::withMessages(['is_gift' => __('checkout.amount_limit')]);
            }
            $giftTax = (int) store_setting('checkout_gift_tax_bps', config('checkout.gift_tax_bps'));
        }

        return app(PriceCalculator::class)->calculate($lines, $promotion['discount'], $shipping['amount'], $giftFee, $giftTax) + ['shipping_method' => $shipping['name'], 'coupon' => $coupon];
    }
}
