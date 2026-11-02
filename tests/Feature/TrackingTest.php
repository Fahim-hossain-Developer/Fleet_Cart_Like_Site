<?php

use App\Domain\Catalog\Models\Product;
use App\Domain\Marketing\Actions\Tracking;
use App\Domain\Marketing\Models\TrackingConsent;
use App\Domain\Marketing\Models\TrackingEvent;
use App\Domain\Orders\Models\Order;
use App\Domain\Settings\Actions\CredentialVault;
use App\Domain\Settings\Actions\Settings;
use App\Jobs\DeliverTrackingEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

function trackingOptIn(): TrackingConsent
{
    app(Settings::class)->set('tracking_enabled', true);
    test()->withHeader('User-Agent', 'Contract Browser')->postJson(route('tracking.consent'), ['accepted' => true])->assertOk();

    return TrackingConsent::firstOrFail();
}
beforeEach(function () {
    Queue::fake();
    Http::preventStrayRequests();
});
it('defaults off and creates no analytics events or third party loaders without explicit consent', function () {
    $p = Product::factory()->create();
    $this->get(route('product', $p->slug))->assertOk()->assertDontSee('googletagmanager.com');
    expect(TrackingEvent::count())->toBe(0);
    app(Settings::class)->set('tracking_enabled', true);
    $this->get(route('product', $p->slug))->assertOk()->assertSee('Optional analytics')->assertDontSee('<script src="https://', false);
    expect(TrackingEvent::count())->toBe(0);
});
it('shares an opaque event ID between browser and server without exposing matching information or credentials', function () {
    $c = trackingOptIn();
    app(Settings::class)->set('tracking_ids', ['meta' => '123456789']);
    app(Settings::class)->set('tracking_capi', true);
    app(CredentialVault::class)->replace('tracking.meta_token', 'server-only-capi-example');
    $p = Product::factory()->create(['price' => 2500]);
    $this->get(route('product', $p->slug))->assertOk();
    $e = TrackingEvent::firstOrFail();
    expect(DB::table('tracking_consents')->value('private_data'))->not->toContain('Contract Browser');
    $browser = $this->postJson(route('tracking.events'))->assertOk()->assertJsonPath('events.0.id', $e->event_id)->assertDontSee('server-only-capi-example')->assertDontSee('Contract Browser');
    $this->postJson(route('tracking.events'))->assertOk()->assertJsonCount(0, 'events');
    Http::fake(['https://graph.facebook.com/v26.0/123456789/events' => Http::response(['events_received' => 1])]);
    (new DeliverTrackingEvent($e->id))->handle();
    (new DeliverTrackingEvent($e->id))->handle();
    Http::assertSentCount(1);
    Http::assertSent(fn ($r) => $r['data'][0]['event_id'] === $e->event_id && $r['data'][0]['event_name'] === 'ViewContent' && $r['data'][0]['custom_data']['value'] === 25);
    expect($e->refresh()->status)->toBe('accepted');
});
it('deduplicates actual live paid purchases and rejects browser forged purchase authority', function () {
    $c = trackingOptIn();
    $p = Product::factory()->create();
    foreach (['unpaid', 'partial', 'sandbox', 'live'] as $mode) {
        $o = Order::factory()->create(['tracking_consent_id' => $c->id, 'payment_environment' => $mode === 'sandbox' ? 'sandbox' : 'live', 'payment_status' => in_array($mode, ['live', 'sandbox']) ? 'paid' : $mode, 'total' => 10000, 'paid_amount' => $mode === 'partial' ? 5000 : ($mode === 'unpaid' ? 0 : 10000)]);
        app(Tracking::class)->purchase($o->id);
        app(Tracking::class)->purchase($o->id);
    }
    expect(TrackingEvent::where('name', 'Purchase')->count())->toBe(1);
    $event = TrackingEvent::where('name', 'Purchase')->firstOrFail();
    expect($event->source_url)->toBe(route('home'))->not->toContain('checkout');
    $this->postJson(route('tracking.events'), ['name' => 'Purchase', 'value' => 999999, 'order_id' => 100])->assertOk();
    expect(TrackingEvent::count())->toBe(1);
});
it('rechecks consent withdrawal before queued server delivery and does not revive older events after reconsent', function () {
    trackingOptIn();
    $p = Product::factory()->create();
    $this->get(route('product', $p->slug));
    $e = TrackingEvent::firstOrFail();
    $this->postJson(route('tracking.consent'), ['accepted' => false])->assertOk();
    expect(TrackingConsent::firstOrFail()->private_data)->toBeNull();
    (new DeliverTrackingEvent($e->id))->handle();
    expect($e->refresh()->status)->toBe('skipped');
    Http::assertNothingSent();
    $this->postJson(route('tracking.consent'), ['accepted' => true])->assertOk();
    $this->postJson(route('tracking.events'))->assertJsonCount(0, 'events');
});
it('retries Meta acceptance uncertainty with the same event ID and bounded attempts', function () {
    trackingOptIn();
    app(Settings::class)->set('tracking_ids', ['meta' => '123456789']);
    app(Settings::class)->set('tracking_capi', true);
    app(CredentialVault::class)->replace('tracking.meta_token', 'server-only-token');
    $p = Product::factory()->create();
    $this->get(route('product', $p->slug));
    $e = TrackingEvent::firstOrFail();
    Http::fake(['*' => Http::sequence()->push([], 503)->push(['events_received' => 1])]);
    (new DeliverTrackingEvent($e->id))->handle();
    expect($e->refresh()->status)->toBe('retry');
    (new DeliverTrackingEvent($e->id))->handle();
    expect($e->refresh()->status)->toBe('accepted');
    Http::assertSentCount(2);
    Http::assertSent(fn ($r) => $r['data'][0]['event_id'] === $e->event_id);
});
it('keeps loaders off sensitive pages and isolates browser events to the consenting session', function () {
    trackingOptIn();
    $p = Product::factory()->create();
    $this->get(route('product', $p->slug));
    $this->get('/login')->assertOk()->assertDontSee('googletagmanager.com')->assertDontSee('analytics.tiktok.com');
    session()->forget('tracking_consent');
    $this->postJson(route('tracking.events'))->assertJsonCount(0, 'events');
});
