<?php

use App\Domain\Customers\Models\StoreNotification;
use App\Domain\Marketing\Actions\SaveCouponReminder;
use App\Domain\Marketing\Models\Coupon;
use App\Domain\Marketing\Models\SavedCoupon;
use App\Jobs\PrepareCouponReminders;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

it('keeps private coupons off offers while allowing a customer to save a known code for one expiry notice', function () {
    Queue::fake();
    $u = User::factory()->create();
    $coupon = Coupon::factory()->create(['code' => 'KNOWNPRIVATE', 'is_public' => false, 'ends_at' => now()->addHours(12)]);
    $this->get('/offers')->assertOk()->assertDontSee('KNOWNPRIVATE');
    $this->actingAs($u, 'web')->post(route('coupon.save'), ['code' => 'knownprivate'])->assertSessionHasNoErrors();
    $this->post(route('coupon.save'), ['code' => 'KNOWNPRIVATE'])->assertSessionHasNoErrors();
    (new PrepareCouponReminders)->handle();
    (new PrepareCouponReminders)->handle();
    expect(SavedCoupon::count())->toBe(1)->and(StoreNotification::count())->toBe(1);
});
it('hides expired or exhausted offers and never sends a reminder for an exhausted coupon', function () {
    Queue::fake();
    $u = User::factory()->create();
    $c = Coupon::factory()->create(['code' => 'EXHAUSTED', 'is_public' => true, 'ends_at' => now()->addHours(12), 'usage_limit' => 1, 'used' => 1]);
    SavedCoupon::create(['user_id' => $u->id, 'coupon_id' => $c->id]);
    $this->get('/offers')->assertOk()->assertDontSee('EXHAUSTED');
    (new PrepareCouponReminders)->handle();
    expect(StoreNotification::count())->toBe(0);
});
it('requires ownership when removing saved reminders', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $coupon = Coupon::factory()->create();
    app(SaveCouponReminder::class)->execute($a, $coupon->code);
    $saved = SavedCoupon::firstOrFail();
    $this->actingAs($b, 'web')->post(route('coupon.remove', $saved->id))->assertNotFound();
    expect($saved->fresh())->not->toBeNull();
});
