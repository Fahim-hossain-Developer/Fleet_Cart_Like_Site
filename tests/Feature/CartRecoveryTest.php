<?php

use App\Domain\Cart\Actions\CartManager;
use App\Domain\Cart\Actions\CartSession;
use App\Domain\Cart\Models\Cart;
use App\Domain\Catalog\Models\Product;
use App\Domain\Customers\Actions\Sms;
use App\Domain\Customers\Models\SmsDelivery;
use App\Domain\Marketing\Actions\CaptureRecoveryContact;
use App\Domain\Marketing\Actions\RecoveryConversion;
use App\Domain\Marketing\Actions\RecoveryPhoneCode;
use App\Domain\Marketing\Actions\SyncMarketingSms;
use App\Domain\Marketing\Models\CartRecoveryContact;
use App\Domain\Marketing\Models\CartRecoveryMessage;
use App\Domain\Settings\Actions\Settings;
use App\Jobs\DeliverCartRecovery;
use App\Jobs\PrepareCartRecovery;
use App\Mail\NewsletterMail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Queue::fake();
    Mail::fake();
    app(Settings::class)->set('recovery_enabled', true);
});

function recoveryCaptureData(): array
{
    return ['enabled' => true, 'consent' => true, 'name' => 'Private Shopper', 'email' => 'recovery@example.test', 'channels' => ['email' => true]];
}
function recoveryFixture(): array
{
    $cart = app(CartSession::class)->current();
    $product = Product::factory()->create(['price' => 10000, 'qty' => 10]);
    app(CartManager::class)->add($cart, $product->id, null, 2);
    $contact = app(CaptureRecoveryContact::class)->execute($cart, recoveryCaptureData());
    $confirmation = CartRecoveryMessage::where('cart_recovery_contact_id', $contact->id)->where('kind', 'confirmation')->firstOrFail();
    $proof = $confirmation->payload['token'];

    return compact('cart', 'product', 'contact', 'confirmation', 'proof');
}
function prepareRecoveryReminder(array $f): array
{
    app(CaptureRecoveryContact::class)->confirm($f['proof']);
    $f['cart']->forceFill(['updated_at' => now()->subHours(2)])->save();
    $f['contact']->update(['last_activity_at' => now()->subHours(2)]);
    (new PrepareCartRecovery)->handle();
    $message = CartRecoveryMessage::where('kind', 'reminder')->firstOrFail();

    return [$message, $message->payload['token']];
}
it('requires consent and encrypts contact proof without creating or verifying accounts', function () {
    $cart = app(CartSession::class)->current();
    $p = Product::factory()->create();
    app(CartManager::class)->add($cart, $p->id, null, 1);
    $this->postJson(route('recovery.capture'), ['enabled' => true])->assertUnprocessable()->assertJsonValidationErrors('consent');
    $this->postJson(route('recovery.capture'), recoveryCaptureData())->assertOk()->assertExactJson(['active' => true, 'email_verified' => false, 'phone_verified' => false]);
    $c = CartRecoveryContact::firstOrFail();
    $m = CartRecoveryMessage::firstOrFail();
    $token = $m->payload['token'];
    expect(DB::table('cart_recovery_contacts')->value('email'))->not->toContain('recovery@example.test');
    expect(DB::table('cart_recovery_messages')->value('payload'))->not->toContain($token);
    expect($c->toJson())->not->toContain('recovery@example.test')->not->toContain('Private Shopper');
    $this->get(route('recovery.confirm', $token))->assertOk()->assertHeader('Referrer-Policy', 'no-referrer')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    $this->get(route('recovery.confirm', $token))->assertGone();
    expect(User::count())->toBe(0);
    $this->assertGuest('web');
});
it('prepares only verified idle carts and sends each stage once', function () {
    $f = recoveryFixture();
    (new PrepareCartRecovery)->handle();
    expect(CartRecoveryMessage::where('kind', 'reminder')->count())->toBe(0);
    [$m,$token] = prepareRecoveryReminder($f);
    (new PrepareCartRecovery)->handle();
    expect(CartRecoveryMessage::where('kind', 'reminder')->count())->toBe(1);
    (new DeliverCartRecovery($m->id))->handle();
    (new DeliverCartRecovery($m->id))->handle();
    Mail::assertSent(NewsletterMail::class, fn ($mail) => str_contains($mail->bodyText, $token));
    Mail::assertSentCount(1);
    expect($m->refresh()->status)->toBe('sent')->and($m->payload)->toBeNull();
});
it('skips old queued messages after cart activity optout conversion or a changed contact generation', function () {
    foreach (['activity', 'stop', 'convert', 'change'] as $case) {
        RateLimiter::clear('recovery-confirm:'.hash_hmac('sha256', 'recovery@example.test', config('app.key')));
        $f = recoveryFixture();
        // Each case gets its own cart and contact.
        [$m] = prepareRecoveryReminder($f);
        if ($case === 'activity') {
            app(CartManager::class)->quantity($f['cart'], $f['cart']->items()->first()->id, 1);
        }
        if ($case === 'stop') {
            app(CaptureRecoveryContact::class)->stop($f['contact']->refresh()->stop_token);
        }
        if ($case === 'convert') {
            app(RecoveryConversion::class)->mark($f['cart']);
        }
        if ($case === 'change') {
            app(CaptureRecoveryContact::class)->execute($f['cart'], array_replace(recoveryCaptureData(), ['email' => 'changed@example.test']));
        }
        (new DeliverCartRecovery($m->id))->handle();
        expect($m->refresh()->status)->toBe('skipped');
        session()->forget('cart_token');
        CartRecoveryMessage::where('kind', 'reminder')->delete();
    }
    Mail::assertNothingSent();
});
it('restores public products at current prices with no account or source cart access and prevents replay', function () {
    $f = recoveryFixture();
    [$m,$token] = prepareRecoveryReminder($f);
    $f['product']->update(['price' => 18000]);
    session()->forget('cart_token');
    $destination = app(CartSession::class)->current();
    $this->get(route('recovery.restore', $token))->assertOk();
    expect($destination->items()->count())->toBe(0);
    $this->post(route('recovery.restore', $token))->assertRedirect(route('cart'));
    $this->post(route('recovery.restore', $token))->assertRedirect(route('cart'));
    expect($destination->items()->count())->toBe(1)->and($destination->items()->first()->qty)->toBe(2)->and(session('cart_token'))->not->toBe($f['cart']->token);
    expect($destination->items()->first()->product->currentPrice())->toBe(18000);
    $this->assertGuest('web');
    app(RecoveryConversion::class)->mark($destination);
    $this->post(route('recovery.restore', $token))->assertGone();
});
it('handles unsubscribe by safe confirmation or an exact stateless capability endpoint', function () {
    $f = recoveryFixture();
    $token = $f['contact']->stop_token;
    $this->get(route('recovery.stop', $token))->assertOk();
    expect($f['contact']->refresh()->stopped_at)->toBeNull();
    $this->post('/integrations/cart-recovery/stop/'.$token)->assertOk()->assertCookieMissing(config('session.cookie'));
    expect($f['contact']->refresh()->stopped_at)->not->toBeNull();
    expect(app(CaptureRecoveryContact::class)->confirm($f['proof']))->toBeFalse();
});
it('does not queue three overdue reminders together after a scheduler outage', function () {
    $f = recoveryFixture();
    app(CaptureRecoveryContact::class)->confirm($f['proof']);
    $f['cart']->forceFill(['updated_at' => now()->subHours(80)])->save();
    $f['contact']->update(['last_activity_at' => now()->subHours(80)]);
    (new PrepareCartRecovery)->handle();
    expect(CartRecoveryMessage::where('kind', 'reminder')->pluck('stage')->all())->toBe([72]);
});
it('tracks SMS provider acceptance separately from placing a message on the queue', function () {
    $f = recoveryFixture();
    $sms = SmsDelivery::create(['uuid' => (string) Str::uuid(), 'driver' => 'sms_log', 'mode' => 'sandbox', 'expires_at' => now()->addMinutes(10)]);
    $m = CartRecoveryMessage::factory()->create(['cart_recovery_contact_id' => $f['contact']->id, 'status' => 'queued_sms', 'sms_delivery_id' => $sms->id]);
    app(SyncMarketingSms::class)->execute();
    expect($m->refresh()->status)->toBe('queued_sms')->and($m->sent_at)->toBeNull();
    $sms->update(['status' => 'sent', 'sent_at' => now()]);
    app(SyncMarketingSms::class)->execute();
    expect($m->refresh()->status)->toBe('sent')->and($m->sent_at)->not->toBeNull();
});
it('keeps capture and phone proof scoped to the current cart and settings restricted to marketing staff', function () {
    $f = recoveryFixture();
    session()->forget('cart_token');
    $this->postJson(route('recovery.code.send'), ['cart_id' => $f['cart']->id])->assertNotFound();
    $this->actingAs(catalogAdmin('support'), 'admin')->get('/admin/cart-recovery-settings')->assertForbidden();
});
it('shows recovery settings and masked records to marketing staff', function () {
    recoveryFixture();
    $this->actingAs(catalogAdmin('manager'), 'admin')->get('/admin/cart-recovery-settings')->assertOk();
    $this->get('/admin/cart-recovery-contacts')->assertOk()->assertDontSee('recovery@example.test');
});
it('requires an accepted purpose-specific SMS code and never turns cart proof into account login', function () {
    $f = recoveryFixture();
    $input = ['enabled' => true, 'consent' => true, 'phone' => '01711111116', 'channels' => ['sms' => true]];
    $c = app(CaptureRecoveryContact::class)->execute($f['cart'], $input);
    app(RecoveryPhoneCode::class)->send($c);
    $c->refresh();
    $sms = SmsDelivery::findOrFail($c->sms_delivery_id);
    preg_match('/[0-9]{6}/', $sms->payload, $match);
    $code = $match[0];
    expect(fn () => app(RecoveryPhoneCode::class)->consume($c, $code))->toThrow(ValidationException::class);
    app(Sms::class)->deliver($sms->id);
    app(RecoveryPhoneCode::class)->consume($c, $code);
    expect($c->refresh()->phone_verified_at)->not->toBeNull()->and(User::count())->toBe(0);
    $this->assertGuest('web');
    expect(fn () => app(RecoveryPhoneCode::class)->consume($c, $code))->toThrow(ValidationException::class);
});
