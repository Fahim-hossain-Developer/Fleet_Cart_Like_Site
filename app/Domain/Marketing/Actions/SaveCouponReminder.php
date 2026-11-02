<?php

namespace App\Domain\Marketing\Actions;

use App\Domain\Marketing\Models\Coupon;
use App\Domain\Marketing\Models\SavedCoupon;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class SaveCouponReminder
{
    public function execute(User $u, string $code): void
    {
        abort_if($u->is_blocked, 403);
        $code = strtoupper(trim($code));
        validator(compact('code'), ['code' => 'required|string|max:50|regex:/^[A-Z0-9_-]+$/D'])->validate();
        $c = Coupon::where('code', $code)->where('is_active', true)->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))->first();
        if (! $c) {
            throw ValidationException::withMessages(['code' => __('checkout.invalid_coupon')]);
        }
        SavedCoupon::upsert([['user_id' => $u->id, 'coupon_id' => $c->id, 'created_at' => now(), 'updated_at' => now()]], ['user_id', 'coupon_id'], ['updated_at']);
    }
}
