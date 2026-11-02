<?php

use App\Domain\Customers\Actions\AdminCode;
use App\Domain\Customers\Models\AdminLoginChallenge;
use App\Domain\Customers\Models\PhoneChallenge;
use App\Domain\Customers\Models\SmsDelivery;
use App\Domain\Orders\Actions\ManageOrder;
use App\Domain\Orders\Actions\PlaceOrder;
use App\Domain\Payments\Actions\PaymentEngine;
use App\Domain\Payments\Models\GatewayAttempt;
use App\Domain\Shipping\Actions\CourierEngine;
use App\Domain\Shipping\Models\CourierBooking;
use App\Domain\Shipping\Models\CourierCollection;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

function integrationRace(array $inputs): array
{
    $workers = [];
    foreach ($inputs as $d) {
        $p = new Process([PHP_BINARY, base_path('tests/Fixtures/integration-worker.php'), json_encode($d, JSON_THROW_ON_ERROR)], base_path(), ['APP_ENV' => 'testing', 'MAIL_MAILER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'array']);
        $p->setTimeout(30);
        $p->start();
        $workers[] = $p;
    }
    $r = [];
    foreach ($workers as $p) {
        $p->wait();
        expect($p->isSuccessful())->toBeTrue($p->getErrorOutput());
        $r[] = json_decode($p->getOutput(), true, 512, JSON_THROW_ON_ERROR)['ok'];
    }

    return $r;
}
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('Integration races require disposable MySQL.');
    }
});
function racePaymentFixture(): array
{
    Queue::fake();
    $f = checkoutFixture();
    $u = User::factory()->create();
    test()->actingAs($u, 'web');
    $o = app(PlaceOrder::class)->execute($f['cart'], $f['data']);
    $o->update(['user_id' => $u->id]);

    return [$o, $u];
}
it('serializes two payment initiations into one unresolved attempt', function () {
    [$o,$u] = racePaymentFixture();
    $d = ['action' => 'start', 'order_id' => $o->id, 'user_id' => $u->id];
    integrationRace([$d + ['key' => (string) Str::uuid()], $d + ['key' => (string) Str::uuid()]]);
    expect(GatewayAttempt::count())->toBe(1);
});
it('credits a verified payment only once across workers', function () {
    [$o,$u] = racePaymentFixture();
    $a = app(PaymentEngine::class)->start($o, 'fake', (string) Str::uuid());
    $a->update(['provider_state' => 'paid']);
    $d = ['action' => 'verify', 'attempt_id' => $a->id];
    integrationRace([$d, $d]);
    expect($o->payments()->count())->toBe(1)->and($o->refresh()->paid_amount)->toBe($o->total);
});
it('reserves and completes only one simultaneous original-provider refund', function () {
    [$o,$u] = racePaymentFixture();
    $a = app(PaymentEngine::class)->start($o, 'fake', (string) Str::uuid());
    $a->update(['provider_state' => 'paid']);
    app(PaymentEngine::class)->verify($a);
    $admin = catalogAdmin();
    test()->actingAs($admin, 'admin');
    app(ManageOrder::class)->transition($o, 'cancelled');
    $d = ['action' => 'refund', 'attempt_id' => $a->id, 'admin_id' => $admin->id, 'amount' => $o->total];
    $r = integrationRace([$d + ['key' => (string) Str::uuid()], $d + ['key' => (string) Str::uuid()]]);
    expect(array_sum($r))->toBe(1)->and($o->refunds()->count())->toBe(1);
});
it('does not create duplicate shipments under simultaneous booking replay', function () {
    [$o,$u] = racePaymentFixture();
    $a = catalogAdmin();
    test()->actingAs($a, 'admin');
    foreach (['confirmed', 'processing', 'packed'] as $s) {
        app(ManageOrder::class)->transition($o, $s);
    }$d = ['action' => 'book', 'order_id' => $o->id, 'admin_id' => $a->id, 'key' => (string) Str::uuid()];
    integrationRace([$d, $d]);
    expect(CourierBooking::count())->toBe(1)->and($o->shipments()->count())->toBe(1);
});
it('caps concurrent courier collections at expected cash', function () {
    [$o,$u] = racePaymentFixture();
    $a = catalogAdmin();
    test()->actingAs($a, 'admin');
    foreach (['confirmed', 'processing', 'packed'] as $s) {
        app(ManageOrder::class)->transition($o, $s);
    }$b = app(CourierEngine::class)->book($o, 'courier_fake', (string) Str::uuid());
    $d = ['action' => 'collect', 'booking_id' => $b->id, 'admin_id' => $a->id, 'amount' => $o->total];
    $r = integrationRace([$d + ['key' => (string) Str::uuid()], $d + ['key' => (string) Str::uuid()]]);
    expect(array_sum($r))->toBe(1)->and(CourierCollection::count())->toBe(1)->and($o->refresh()->paid_amount)->toBe($o->total);
});
it('consumes a phone code once under concurrent account creation', function () {
    $phone = '+8801711111188';
    $sms = SmsDelivery::create(['uuid' => (string) Str::uuid(), 'driver' => 'sms_log', 'mode' => 'sandbox', 'receiver' => $phone, 'status' => 'sent', 'sent_at' => now()]);
    PhoneChallenge::create(['scope_key' => hash('sha256', 'login:'.$phone), 'phone' => $phone, 'purpose' => 'login', 'code_hash' => Hash::make('123456'), 'expires_at' => now()->addMinutes(10), 'sms_delivery_id' => $sms->id]);
    $d = ['action' => 'phone', 'phone' => $phone];
    $r = integrationRace([$d, $d]);
    expect(array_sum($r))->toBe(1)->and(User::where('phone', $phone)->count())->toBe(1);
});
it('consumes an admin second factor only once across workers', function () {
    $a = catalogAdmin();
    $uuid = (string) Str::uuid();
    AdminLoginChallenge::create(['uuid' => $uuid, 'admin_id' => $a->id, 'code_hash' => Hash::make('123456'), 'password_fingerprint' => app(AdminCode::class)->fingerprint($a), 'expires_at' => now()->addMinutes(10), 'sent_at' => now()]);
    $d = ['action' => 'admin', 'uuid' => $uuid];
    $r = integrationRace([$d, $d]);
    expect(array_sum($r))->toBe(1);
});
