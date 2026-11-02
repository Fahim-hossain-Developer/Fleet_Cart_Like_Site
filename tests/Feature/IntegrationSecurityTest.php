<?php

use App\Domain\Customers\Actions\AdminCode;
use App\Domain\Customers\Actions\PhoneCode;
use App\Domain\Customers\Actions\Sms;
use App\Domain\Customers\Models\AdminLoginChallenge;
use App\Domain\Customers\Models\PhoneChallenge;
use App\Domain\Customers\Models\SmsDelivery;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Actions\PaymentEngine;
use App\Domain\Settings\Actions\CredentialVault;
use App\Domain\Settings\Actions\IntegrationConfiguration;
use App\Domain\Settings\Actions\Settings;
use App\Filament\Auth\Login;
use App\Filament\Pages\IntegrationSettings;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function phoneProof(string $phone, ?User $owner = null): string
{
    Queue::fake();
    app(PhoneCode::class)->send($phone, $owner);
    $c = PhoneChallenge::latest('id')->firstOrFail();
    $d = SmsDelivery::findOrFail($c->sms_delivery_id);
    preg_match('/[0-9]{6}/', $d->payload, $m);
    app(Sms::class)->deliver($d->id);

    return $m[0];
}
it('consumes a delivered SMS code once creates a phone account and links only unowned matching orders', function () {
    $f = checkoutFixture();
    $this->post('/checkout', $f['data'])->assertRedirect();
    $o = Order::firstOrFail();
    $code = phoneProof($o->phone);
    $c = PhoneChallenge::firstOrFail();
    expect(Hash::check($code, $c->code_hash))->toBeTrue()->and($c->code_hash)->not->toBe($code);
    $u = app(PhoneCode::class)->consume($o->phone, $code);
    expect($u->phone_verified_at)->not->toBeNull()->and($o->refresh()->user_id)->toBe($u->id);
    expect(fn () => app(PhoneCode::class)->consume($o->phone, $code))->toThrow(ValidationException::class);
    expect(SmsDelivery::first()->payload)->toBeNull()->and(SmsDelivery::first()->receiver)->toBeNull();
});
it('does not let an unverified phone claim an email verified account or reset its password', function () {
    Queue::fake();
    $u = User::factory()->create(['phone' => '+8801711111111', 'password' => 'SecretPassword123!']);
    app(PhoneCode::class)->send($u->phone);
    expect(PhoneChallenge::count())->toBe(0);
    $code = phoneProof($u->phone, $u);
    $verified = app(PhoneCode::class)->consume($u->phone, $code, $u);
    expect(Hash::check('SecretPassword123!', $verified->password))->toBeTrue();
    $loginCode = phoneProof($u->phone);
    expect(app(PhoneCode::class)->consume($u->phone, $loginCode)->id)->toBe($u->id);
});
it('revokes a preverification phone-only password and persists the five-attempt limit', function () {
    $u = User::factory()->unverified()->create(['phone' => '+8801711111112', 'password' => 'OldPassword123!']);
    $code = phoneProof($u->phone);
    $u = app(PhoneCode::class)->consume($u->phone, $code);
    expect(Hash::check('OldPassword123!', $u->password))->toBeFalse()->and($u->auth_version)->toBe(1)->and($u->email)->toBeNull();
    $code = phoneProof($u->phone);
    for ($i = 0; $i < 5; $i++) {
        expect(fn () => app(PhoneCode::class)->consume($u->phone, '000000'))->toThrow(ValidationException::class);
    }
    expect(fn () => app(PhoneCode::class)->consume($u->phone, $code))->toThrow(ValidationException::class);
    expect(PhoneChallenge::first()->attempts)->toBe(5);
});
it('does not accept undelivered expired or local simulation codes in production', function () {
    Queue::fake();
    app(PhoneCode::class)->send('+8801711111113');
    $c = PhoneChallenge::firstOrFail();
    $d = SmsDelivery::firstOrFail();
    preg_match('/[0-9]{6}/', $d->payload, $m);
    expect(fn () => app(PhoneCode::class)->consume($c->phone, $m[0]))->toThrow(ValidationException::class);
    app(Sms::class)->deliver($d->id);
    app()->detectEnvironment(fn () => 'production');
    expect(fn () => app(PhoneCode::class)->consume($c->phone, $m[0]))->toThrow(ValidationException::class);
});
it('encrypts pending SMS and follows the documented Twilio message contract', function () {
    Queue::fake();
    $this->actingAs(catalogAdmin(), 'admin');
    $config = app(IntegrationConfiguration::class);
    $sid = 'AC'.str_repeat('a', 32);
    $config->save('sms_twilio', ['is_enabled' => true, 'mode' => 'sandbox', 'minimum' => 1, 'maximum' => 100000, 'documentation_url' => null], ['account_sid' => $sid, 'auth_token' => 'PRIVATE_SMS_TOKEN', 'from' => '+12025550123']);
    app(Settings::class)->set('sms_driver', 'sms_twilio');
    Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM'.str_repeat('b', 32)], 201)]);
    $d = app(Sms::class)->queue('+8801711111114', 'PRIVATE_SMS_BODY');
    expect(DB::table('sms_deliveries')->value('payload'))->not->toContain('PRIVATE_SMS_BODY');
    app(Sms::class)->deliver($d->id);
    expect($d->refresh()->status)->toBe('sent')->and($d->payload)->toBeNull();
    Http::assertSent(fn ($r) => $r->url() === 'https://api.twilio.com/2010-04-01/Accounts/'.$sid.'/Messages.json' && $r['To'] === '+8801711111114' && $r['Body'] === 'PRIVATE_SMS_BODY');
});
it('keeps future providers disabled while saving encrypted configuration and documentation for later', function () {
    $this->actingAs(catalogAdmin(), 'admin');
    $config = app(IntegrationConfiguration::class);
    $data = ['is_enabled' => false, 'mode' => 'sandbox', 'documentation_url' => 'https://example.com/official-docs', 'minimum' => 1, 'maximum' => 100000];
    $config->save('nagad', $data, ['private_configuration_json' => '{"key":"FUTURE_PRIVATE_SENTINEL"}']);
    expect($config->connection('nagad')->config['documentation_url'])->toBe($data['documentation_url'])->and($config->ready('nagad'))->toBeFalse();
    expect(DB::table('settings')->where('key', 'credential.integration.nagad.sandbox.private_configuration_json')->value('encrypted_value'))->not->toContain('FUTURE_PRIVATE_SENTINEL');
    expect(fn () => $config->save('nagad', array_replace($data, ['is_enabled' => true]), []))->toThrow(ValidationException::class);
});
it('clears submitted secrets from Livewire state and denies managers credential access', function () {
    $this->actingAs(catalogAdmin(), 'admin');
    Livewire::test(IntegrationSettings::class)->set('driver', 'stripe')->set('data.is_enabled', true)->set('data.credentials.api_key', 'PRIVATE_UI_SENTINEL')->set('data.credentials.webhook_secret', 'PRIVATE_WEBHOOK_SENTINEL')->call('submit')->assertHasNoErrors()->assertSet('data.credentials', [])->assertDontSee('PRIVATE_UI_SENTINEL');
    expect(app(CredentialVault::class)->get('integration.stripe.sandbox.api_key'))->toBe('PRIVATE_UI_SENTINEL');
    $this->actingAs(catalogAdmin('manager'), 'admin')->get('/admin/integration-settings')->assertForbidden();
});
it('requires admin email OTP before authenticating and enforces it on direct admin routes', function () {
    $a = catalogAdmin();
    app(Settings::class)->set('admin_otp_roles', ['super-admin']);
    Queue::fake();
    Mail::fake();
    Livewire::test(Login::class)->fillForm(['email' => $a->email, 'password' => 'password'])->call('authenticate');
    // Fixture passwords are random; use a known password and retry a clean login.
    $a->password = 'KnownAdminPassword123!';
    $a->save();
    Livewire::test(Login::class)->fillForm(['email' => $a->email, 'password' => 'KnownAdminPassword123!'])->call('authenticate')->assertRedirect(route('admin.otp'));
    expect(auth('admin')->check())->toBeFalse();
    $c = AdminLoginChallenge::firstOrFail();
    $code = $c->delivery_payload;
    app(AdminCode::class)->deliver($c->id);
    $this->post(route('admin.otp.verify'), ['code' => $code])->assertRedirect('/admin');
    expect(auth('admin')->id())->toBe($a->id);
    $this->get('/admin')->assertOk();
    session()->forget('admin_otp_verified');
    $this->get('/admin/commerce-export/orders')->assertRedirect('/admin/login');
});
it('invalidates admin codes after password changes and consumes codes only once', function () {
    Queue::fake();
    Mail::fake();
    $a = catalogAdmin();
    $c = app(AdminCode::class)->begin($a);
    $code = $c->delivery_payload;
    app(AdminCode::class)->deliver($c->id);
    $a->password = 'ChangedPassword123!';
    $a->save();
    expect(fn () => app(AdminCode::class)->consume($c->uuid, $code))->toThrow(ValidationException::class);
    $c = app(AdminCode::class)->begin($a);
    $code = $c->delivery_payload;
    app(AdminCode::class)->deliver($c->id);
    app(AdminCode::class)->consume($c->uuid, $code);
    expect(fn () => app(AdminCode::class)->consume($c->uuid, $code))->toThrow(ValidationException::class);
});

it('keeps provider secrets and encrypted delivery payloads off all new public and customer pages', function () {
    $sentinel = 'PRIVATE_ALL_PROVIDER_SENTINEL_abc123';
    $vault = app(CredentialVault::class);
    foreach (IntegrationConfiguration::PROVIDERS as $driver => $meta) {
        foreach ($meta['keys'] as $key) {
            $vault->replace('integration.'.$driver.'.sandbox.'.$key, $sentinel);
        }
    }
    Queue::fake();
    $f = checkoutFixture();
    $this->post('/checkout', $f['data'])->assertRedirect();
    $o = Order::firstOrFail();
    $a = app(PaymentEngine::class)->start($o, 'fake', (string) Str::uuid());
    foreach (['/', '/checkout/success/'.$o->number, '/orders/'.$o->number.'/pay', '/payments/'.$a->uuid, $a->redirect_url, '/phone-code', '/email-code', '/login'] as $uri) {
        expect($this->get($uri)->assertOk()->getContent())->not->toContain($sentinel)->not->toContain('encrypted_value')->not->toContain('callback_proof_hash');
    }
    expect(DB::table('activity_log')->get()->toJson())->not->toContain($sentinel);
});

it('commits failed admin code attempts and rejects expired challenges', function () {
    Queue::fake();
    Mail::fake();
    $a = catalogAdmin();
    $c = app(AdminCode::class)->begin($a);
    $code = $c->delivery_payload;
    app(AdminCode::class)->deliver($c->id);
    for ($i = 0; $i < 5; $i++) {
        expect(fn () => app(AdminCode::class)->consume($c->uuid, '000000'))->toThrow(ValidationException::class);
    }
    expect(fn () => app(AdminCode::class)->consume($c->uuid, $code))->toThrow(ValidationException::class);
    expect($c->refresh()->attempts)->toBe(5);
    $c = app(AdminCode::class)->begin($a);
    $code = $c->delivery_payload;
    app(AdminCode::class)->deliver($c->id);
    $c->update(['expires_at' => now()->subSecond()]);
    expect(fn () => app(AdminCode::class)->consume($c->uuid, $code))->toThrow(ValidationException::class);
});
it('clears private credential form state even when pending provider activation is rejected', function () {
    $this->actingAs(catalogAdmin(), 'admin');
    Livewire::test(IntegrationSettings::class)->set('driver', 'nagad')->set('data.credentials.private_configuration_json', '{"secret":"PRIVATE_REJECTED_SENTINEL"}')->set('data.is_enabled', true)->call('submit')->assertHasErrors('is_enabled')->assertSet('data.credentials', [])->assertDontSee('PRIVATE_REJECTED_SENTINEL');
});

it('preserves admin independence without allowing a blocked customer into account pages', function () {
    $a = catalogAdmin();
    $u = User::factory()->create(['is_blocked' => true]);
    $this->actingAs($a, 'admin')->actingAs($u, 'web');
    $this->get('/account')->assertForbidden();
    expect(auth('admin')->id())->toBe($a->id)->and(auth('web')->check())->toBeFalse();
    $this->get('/admin')->assertOk();
});
