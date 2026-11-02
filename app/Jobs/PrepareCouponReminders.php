<?php

namespace App\Jobs;

use App\Domain\Customers\Actions\PublishNotice;
use App\Domain\Marketing\Models\Coupon;
use App\Domain\Marketing\Models\SavedCoupon;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class PrepareCouponReminders implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        SavedCoupon::orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $saved) {
                $c = Coupon::whereKey($saved->coupon_id)->where('is_active', true)->where('ends_at', '>', now())->where('ends_at', '<=', now()->addDay())->first();
                $u = User::find($saved->user_id);
                if (! $c || ! $u || $u->is_blocked || ($c->usage_limit !== null && $c->used >= $c->usage_limit)) {
                    continue;
                }app(PublishNotice::class)->execute($u, 'coupons', 'marketing.notice_coupon_expiry', ['code' => $c->code, 'time' => $c->ends_at->format('d M Y H:i')], route('cart', [], false), 'saved-coupon:'.$saved->id.':'.$c->ends_at->timestamp);
            }
        });
    }
}
