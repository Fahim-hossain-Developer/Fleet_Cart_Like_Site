<?php

use App\Domain\Orders\Actions\EditOrderItems;
use App\Domain\Orders\Actions\ManageOrder;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Actions\CallbackToken;
use App\Domain\Payments\Actions\IntegrationFailure;
use App\Domain\Payments\Actions\PaymentEngine;
use App\Domain\Payments\Actions\ProviderMoney;
use App\Domain\Payments\Actions\RecordPaymentReceipt;
use App\Domain\Payments\Actions\RefundEngine;
use App\Domain\Payments\Models\GatewayAttempt;
use App\Domain\Settings\Actions\CredentialVault;
use App\Domain\Settings\Actions\Settings;
use App\Domain\Settings\Models\IntegrationConnection;
use App\Domain\Settings\Models\IntegrationEvent;
use App\Jobs\ProcessPaymentEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

function paymentFixture(string $method = 'fake'): array
{
    Queue::fake();
    $f = checkoutFixture();
    test()->post('/checkout', array_replace($f['data'], ['payment_method' => $method, 'paid_amount' => 999999999]))->assertRedirect();
    $f['order'] = Order::firstOrFail();

    return $f;
}
function beginFakePayment(Order $order, string $purpose = 'full'): GatewayAttempt
{
    test()->post(route('payments.start', $order->number), ['driver' => 'fake', 'key' => (string) Str::uuid(), 'purpose' => $purpose, 'amount' => 1])->assertRedirect();

    return GatewayAttempt::latest('id')->firstOrFail();
}
it('uses server amounts and verifies a local test payment without a second stock deduction', function () {
    $f = paymentFixture();
    $o = $f['order'];
    expect($o->payment_status)->toBe('unpaid');
    $a = beginFakePayment($o);
    expect($a->status)->toBe('initiated')->and($a->amount)->toBe(108000);
    $this->get($a->redirect_url)->assertOk()->assertSee('Local test payment gateway');
    $this->post($a->redirect_url, ['outcome' => 'paid'])->assertRedirect();
    expect($a->refresh()->status)->toBe('succeeded')->and($o->refresh()->paid_amount)->toBe(108000)->and($o->payment_environment)->toBe('sandbox')->and($f['product']->refresh()->qty)->toBe(9);
    $this->get(route('payments.status', $a->uuid))->assertOk()->assertSee('Sandbox / local test payment');
});
it('replays initiation and completion idempotently and hides provider URLs in serialization', function () {
    $f = paymentFixture();
    $key = (string) Str::uuid();
    $a = app(PaymentEngine::class)->start($f['order'], 'fake', $key);
    $again = app(PaymentEngine::class)->start($f['order'], 'fake', $key);
    expect($again->id)->toBe($a->id)->and($a->toArray())->not->toHaveKey('redirect_url');
    $this->post($a->redirect_url, ['outcome' => 'paid'])->assertRedirect();
    $this->post($a->redirect_url, ['outcome' => 'cancelled'])->assertRedirect();
    expect($f['order']->payments()->count())->toBe(1)->and($f['order']->events()->where('type', 'payment')->count())->toBe(1);
});
it('does not accept browser success as payment proof and keeps return callbacks stateless', function () {
    $f = paymentFixture();
    $a = beginFakePayment($f['order']);
    $url = app(CallbackToken::class)->url($a);
    $response = $this->post($url.'?status=success', ['amount' => 108000, 'status' => 'success']);
    $response->assertRedirect(route('payments.status', $a->uuid))->assertCookieMissing(config('session.cookie'));
    expect($f['order']->refresh()->paid_amount)->toBe(0);
    $this->get(route('payments.status', $a->uuid))->assertOk();
});
it('protects payment and test pages with receipt ownership and disables simulation in production', function () {
    $f = paymentFixture();
    $a = beginFakePayment($f['order']);
    $this->flushSession();
    $this->actingAs(User::factory()->create(), 'web');
    $this->get(route('payments.status', $a->uuid))->assertForbidden();
    $this->get($a->redirect_url)->assertForbidden();
    app()->detectEnvironment(fn () => 'production');
    $this->get($a->redirect_url)->assertForbidden();
    expect($f['order']->refresh()->paid_amount)->toBe(0);
});
it('releases failed unpaid stock once and preserves evidence of a late settled payment for review', function () {
    $f = paymentFixture();
    $o = $f['order'];
    $a = beginFakePayment($o);
    $this->post($a->redirect_url, ['outcome' => 'failed'])->assertRedirect();
    expect($o->refresh()->status)->toBe('failed')->and($f['product']->refresh()->qty)->toBe(10);
    $this->post($a->redirect_url, ['outcome' => 'paid'])->assertRedirect();
    expect($o->refresh()->paid_amount)->toBe(0)->and($f['product']->refresh()->qty)->toBe(10);
    // A later authoritative provider settlement is evidence, not permission to reopen/re-reserve a failed order.
    $a->update(['provider_state' => 'paid']);
    app(PaymentEngine::class)->verify($a);
    expect($a->refresh()->status)->toBe('review_required')->and($a->verified_amount)->toBe(108000)->and($o->refresh()->paid_amount)->toBe(0);
    $this->actingAs(catalogAdmin(), 'admin');
    $r = app(RefundEngine::class)->start($a, 108000, 'Return unapplied test funds', (string) Str::uuid());
    expect($r->status)->toBe('succeeded')->and($a->refresh()->status)->toBe('refunded_unapplied')->and($o->refunds()->count())->toBe(0);
});
it('blocks payment-time edits cancellation manual collection and dispatch while a payment is unresolved', function () {
    $f = paymentFixture();
    $o = $f['order'];
    $a = beginFakePayment($o);
    $u = User::factory()->create();
    $o->update(['user_id' => $u->id]);
    $this->actingAs($u, 'web')->post('/account/orders/'.$o->number, ['action' => 'cancel'])->assertSessionHasErrors('order');
    $this->actingAs(catalogAdmin(), 'admin');
    expect(fn () => app(ManageOrder::class)->payment($o, 100, 'receipt', (string) Str::uuid()))->toThrow(ValidationException::class);
    expect(fn () => app(EditOrderItems::class)->execute($o, [$o->items()->first()->id => 2]))->toThrow(ValidationException::class);
    foreach (['confirmed', 'processing', 'packed'] as $s) {
        app(ManageOrder::class)->transition($o, $s);
    }
    expect(fn () => app(ManageOrder::class)->ship($o, [$o->items()->first()->id => 1], 'Manual', 'TRACK', (string) Str::uuid()))->toThrow(ValidationException::class);
});
it('snapshots COD advance and records only the required advance before dispatch', function () {
    app(Settings::class)->set('cod_advance_mode', 'delivery');
    $f = paymentFixture('cod');
    $o = $f['order'];
    expect($o->advance_required)->toBe(8000);
    app(Settings::class)->set('cod_advance_mode', 'off');
    $this->actingAs(catalogAdmin(), 'admin');
    foreach (['confirmed', 'processing', 'packed'] as $s) {
        app(ManageOrder::class)->transition($o, $s);
    }expect(fn () => app(ManageOrder::class)->ship($o, [$o->items()->first()->id => 1], 'Manual', 'TRACK', (string) Str::uuid()))->toThrow(ValidationException::class);
    $a = beginFakePayment($o, 'due');
    expect($a->amount)->toBe(8000);
    $this->post($a->redirect_url, ['outcome' => 'paid'])->assertRedirect();
    expect($o->refresh()->paid_amount)->toBe(8000)->and($o->payment_status)->toBe('partial');
    app(ManageOrder::class)->ship($o, [$o->items()->first()->id => 1], 'Manual', 'TRACK', (string) Str::uuid());
    expect($o->refresh()->status)->toBe('shipped');
});
it('records verified money even when deferred stock is unavailable and holds fulfillment', function () {
    app(Settings::class)->set('orders_stock_policy', 'payment');
    $f = paymentFixture();
    $o = $f['order'];
    $a = beginFakePayment($o);
    $f['product']->update(['qty' => 0]);
    $this->post($a->redirect_url, ['outcome' => 'paid'])->assertRedirect();
    expect($o->refresh()->paid_amount)->toBe(108000)->and($o->status)->toBe('on-hold')->and($o->risk_flags)->toContain('paid_stock_unavailable')->and($f['product']->refresh()->qty)->toBe(0);
});
it('records an original-provider test refund once with the same cancellation and stock rules', function () {
    $f = paymentFixture();
    $o = $f['order'];
    $a = beginFakePayment($o);
    $this->post($a->redirect_url, ['outcome' => 'paid'])->assertRedirect();
    $this->actingAs(catalogAdmin(), 'admin');
    app(ManageOrder::class)->transition($o, 'cancelled');
    $key = (string) Str::uuid();
    $r = app(RefundEngine::class)->start($a->refresh(), 108000, 'Cancelled', $key);
    $again = app(RefundEngine::class)->start($a->refresh(), 108000, 'Cancelled', $key);
    expect($r->status)->toBe('succeeded')->and($again->id)->toBe($r->id)->and($o->refunds()->count())->toBe(1)->and($o->refresh()->refunded_amount)->toBe(108000)->and($f['product']->refresh()->qty)->toBe(10);
});
it('authenticates and deduplicates Stripe events and still verifies exact server amounts', function () {
    $f = paymentFixture('cod');
    $vault = app(CredentialVault::class);
    $vault->replace('integration.stripe.sandbox.api_key', 'sk_test_PRIVATE_SENTINEL');
    $vault->replace('integration.stripe.sandbox.webhook_secret', 'whsec_PRIVATE_SENTINEL');
    IntegrationConnection::create(['driver' => 'stripe', 'kind' => 'payment', 'mode' => 'sandbox', 'is_enabled' => true, 'config' => ['minimum' => 1, 'maximum' => 100000000000]]);
    Http::fake(function ($r) {
        $a = GatewayAttempt::where('driver', 'stripe')->firstOrFail();
        if ($r->method() === 'POST') {
            return Http::response(['id' => 'cs_phase4', 'url' => 'https://checkout.stripe.com/c/pay/test']);
        }

        return Http::response(['id' => 'cs_phase4', 'livemode' => false, 'client_reference_id' => $a->merchant_reference, 'payment_intent' => 'pi_phase4', 'amount_total' => $a->amount, 'currency' => 'bdt', 'payment_status' => 'paid', 'status' => 'complete']);
    });
    $a = app(PaymentEngine::class)->start($f['order'], 'stripe', (string) Str::uuid());
    $payload = ['id' => 'evt_phase4', 'type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'cs_phase4', 'client_reference_id' => $a->merchant_reference]]];
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $time = now()->timestamp;
    $signature = 't='.$time.',v1='.hash_hmac('sha256', $time.'.'.$body, 'whsec_PRIVATE_SENTINEL');
    $uri = '/integrations/payments/stripe/sandbox/webhook';
    $this->withHeaders(['Stripe-Signature' => $signature])->postJson($uri, $payload)->assertOk();
    $event = IntegrationEvent::firstOrFail();
    (new ProcessPaymentEvent($event->id))->handle();
    $this->withHeaders(['Stripe-Signature' => $signature])->postJson($uri, $payload)->assertOk();
    (new ProcessPaymentEvent($event->id))->handle();
    expect(IntegrationEvent::count())->toBe(1)->and($f['order']->payments()->count())->toBe(1)->and($f['order']->refresh()->paid_amount)->toBe(108000);
    $this->withHeaders(['Stripe-Signature' => 't='.$time.',v1=forged'])->postJson($uri, array_replace($payload, ['id' => 'evt_forged']))->assertForbidden();
    $this->get(route('payments.status', $a->uuid))->assertOk()->assertDontSee('sk_test_PRIVATE_SENTINEL')->assertDontSee('whsec_PRIVATE_SENTINEL');
});
it('rejects excess precision and does not reinterpret provider amounts as binary floating point totals', function () {
    expect(ProviderMoney::minor('100.01'))->toBe(10001)->and(ProviderMoney::decimal(10001))->toBe('100.01');
    foreach (['1.001', '-1', '1e3', 'NaN'] as $value) {
        expect(fn () => ProviderMoney::minor($value))->toThrow(IntegrationFailure::class);
    }
});

it('keeps an order marked as test after mixed-mode receipts and forbids production fulfillment', function () {
    $f = paymentFixture('cod');
    $o = $f['order'];
    $this->actingAs(catalogAdmin(), 'admin');
    DB::transaction(function () use ($o) {
        $locked = Order::lockForUpdate()->findOrFail($o->id);
        app(RecordPaymentReceipt::class)->execute($locked, 100, 'TEST-ADVANCE', hash('sha256', 'test-advance'), null, 'fake:sandbox', 'TEST-ADVANCE', 'sandbox');
    });
    DB::transaction(function () use ($o) {
        $locked = Order::lockForUpdate()->findOrFail($o->id);
        app(RecordPaymentReceipt::class)->execute($locked, 100, 'LIVE-RECEIPT', hash('sha256', 'live-receipt'), null, 'stripe:live', 'LIVE-RECEIPT', 'live');
    });
    expect($o->refresh()->payment_environment)->toBe('sandbox');
    foreach (['confirmed', 'processing', 'packed'] as $s) {
        app(ManageOrder::class)->transition($o, $s);
    }
    app()->detectEnvironment(fn () => 'production');
    expect(fn () => app(ManageOrder::class)->ship($o, [$o->items()->first()->id => 1], 'Manual', 'REAL', (string) Str::uuid()))->toThrow(ValidationException::class);
});
