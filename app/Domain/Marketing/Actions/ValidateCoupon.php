<?php

namespace App\Domain\Marketing\Actions;

use App\Domain\Marketing\Models\Coupon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ValidateCoupon
{
    /** @return array{discount:int,free_shipping:bool} */
    public function execute(?Coupon $coupon, int $subtotal, ?string $phone = null): array
    {
        if (! $coupon) {
            return ['discount' => 0, 'free_shipping' => false];
        }
        $bad = ! $coupon->is_active || ($coupon->starts_at && $coupon->starts_at->isFuture()) || ($coupon->ends_at && $coupon->ends_at->isPast()) || $subtotal < $coupon->minimum || ($coupon->usage_limit !== null && $coupon->used >= $coupon->usage_limit);
        if ($phone && $coupon->per_customer_limit !== null) {
            $bad = $bad || DB::table('coupon_usages')->where('coupon_id', $coupon->id)->where('identity_hash', $this->identity($phone))->count() >= $coupon->per_customer_limit;
        }
        if ($bad) {
            throw ValidationException::withMessages(['coupon' => __('checkout.invalid_coupon')]);
        }
        $discount = match ($coupon->type) {
            'percent' => intdiv($subtotal * min(100, $coupon->value) + 50, 100),'fixed' => min($subtotal, $coupon->value),default => 0
        };
        if ($coupon->max_discount !== null) {
            $discount = min($discount, $coupon->max_discount);
        }

        return ['discount' => $discount, 'free_shipping' => $coupon->type === 'free_shipping'];
    }

    public function identity(string $phone): string
    {
        return hash_hmac('sha256', $phone, (string) config('app.key'));
    }
}
