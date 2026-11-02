<?php

use App\Domain\Catalog\Models\Product;
use App\Domain\Customers\Actions\NoticePreferences;
use App\Domain\Customers\Actions\PublishNotice;
use App\Domain\Customers\Actions\WatchProduct;
use App\Domain\Customers\Models\NoticeDelivery;
use App\Domain\Customers\Models\ProductWatch;
use App\Domain\Customers\Models\StoreNotification;
use App\Domain\Marketing\Actions\ManageReviews;
use App\Domain\Marketing\Models\Review;
use App\Jobs\DeliverNotice;
use App\Jobs\PublishFeedbackNotice;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

it('deduplicates real notices and restricts read operations to the actual owner', function () {
    Queue::fake();
    $u = User::factory()->create();
    $n = app(PublishNotice::class)->execute($u, 'orders', 'marketing.notice_order', ['number' => 'ORDER-1'], '/account', 'order:event:1', false);
    app(PublishNotice::class)->execute($u, 'orders', 'marketing.notice_order', ['number' => 'ORDER-1'], '/account', 'order:event:1', false);
    expect(StoreNotification::count())->toBe(1);
    $this->actingAs($u, 'web')->get('/account/notifications')->assertOk()->assertSee('ORDER-1');
    $this->post(route('notifications.read', $n->id))->assertRedirect();
    expect($n->refresh()->read_at)->not->toBeNull();
    $this->flushSession();
    $other = User::factory()->create();
    $this->actingAs($other, 'web')->get('/account/notifications')->assertDontSee('ORDER-1');
    $this->post(route('notifications.read', $n->id))->assertForbidden();
});
it('requires opt-in and verified contacts before sending marketing email or SMS', function () {
    Queue::fake();
    Mail::fake();
    $u = User::factory()->unverified()->create();
    app(PublishNotice::class)->execute($u, 'reviews', 'marketing.notice_stock', ['product' => 'A product'], '/shop', 'review:1');
    expect(NoticeDelivery::count())->toBe(0);
    Mail::assertNothingSent();
    expect(fn () => app(NoticePreferences::class)->save($u, ['preferences' => ['reviews' => ['email' => true]]]))->toThrow(ValidationException::class);
    $u->forceFill(['email_verified_at' => now()])->save();
    app(NoticePreferences::class)->save($u, ['preferences' => ['reviews' => ['email' => true, 'in_app' => true]]]);
    $n = app(PublishNotice::class)->execute($u, 'reviews', 'marketing.notice_stock', ['product' => 'A product'], '/shop', 'review:2');
    $d = NoticeDelivery::firstOrFail();
    (new DeliverNotice($d->id))->handle();
    expect($d->refresh()->status)->toBe('sent');
    Mail::assertSentCount(1);
    app(PublishNotice::class)->execute($u, 'reviews', 'marketing.notice_stock', ['product' => 'A product'], '/shop', 'review:2');
    (new DeliverNotice($d->id))->handle();
    Mail::assertSentCount(1);
});
it('rechecks notification preferences before a queued message is accepted', function () {
    Queue::fake();
    Mail::fake();
    $u = User::factory()->create();
    app(NoticePreferences::class)->save($u, ['preferences' => ['price' => ['email' => true]]]);
    app(PublishNotice::class)->execute($u, 'price', 'marketing.notice_price', ['product' => 'A product'], '/shop', 'price:1');
    $d = NoticeDelivery::firstOrFail();
    app(NoticePreferences::class)->save($u, ['preferences' => []]);
    (new DeliverNotice($d->id))->handle();
    expect($d->refresh()->status)->toBe('skipped');
    Mail::assertNothingSent();
});
it('notifies only on actual availability and price transitions and does not repeat the same state', function () {
    Queue::fake();
    $u = User::factory()->create();
    $p = Product::factory()->create(['qty' => 0, 'price' => 100000]);
    app(WatchProduct::class)->set($u, $p, 'stock', true);
    $w = ProductWatch::firstOrFail();
    app(WatchProduct::class)->check($w->id);
    expect(StoreNotification::count())->toBe(0);
    $p->update(['qty' => 5]);
    app(WatchProduct::class)->check($w->id);
    app(WatchProduct::class)->check($w->id);
    expect(StoreNotification::count())->toBe(1);
    app(WatchProduct::class)->set($u, $p->refresh(), 'price', true);
    $priceWatch = ProductWatch::where('kind', 'price')->first();
    $p->update(['special_price' => 80000]);
    app(WatchProduct::class)->check($priceWatch->id);
    app(WatchProduct::class)->check($priceWatch->id);
    expect(StoreNotification::count())->toBe(2)->and(NoticeDelivery::count())->toBe(0);
});

it('retries a committed review reply notice without publishing duplicates', function () {
    Queue::fake();
    $u = User::factory()->create();
    $p = Product::factory()->create();
    $r = Review::factory()->create(['user_id' => $u->id, 'product_id' => $p->id]);
    $this->actingAs(catalogAdmin('manager'), 'admin');
    app(ManageReviews::class)->moderate($r, 'approved', 'A real store reply.');
    expect($r->refresh()->notice_pending)->toBeTrue();
    (new PublishFeedbackNotice('review', $r->id))->handle();
    (new PublishFeedbackNotice('review', $r->id))->handle();
    expect($r->refresh()->notice_pending)->toBeFalse()->and(StoreNotification::count())->toBe(1);
});
