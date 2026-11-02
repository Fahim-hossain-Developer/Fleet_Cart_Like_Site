<?php

use App\Domain\Orders\Actions\ManageOrder;
use App\Domain\Orders\Actions\PlaceOrder;
use App\Domain\Orders\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

function orderManagementRace(array $inputs): array
{
    $workers = [];
    foreach ($inputs as $data) {
        $p = new Process([PHP_BINARY, base_path('tests/Fixtures/manage-order-worker.php'), json_encode($data, JSON_THROW_ON_ERROR)], base_path(), ['APP_ENV' => 'testing', 'MAIL_MAILER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'array']);
        $p->setTimeout(30);
        $p->start();
        $workers[] = $p;
    }
    $results = [];
    foreach ($workers as $p) {
        $p->wait();
        expect($p->isSuccessful())->toBeTrue($p->getErrorOutput());
        $results[] = json_decode($p->getOutput(), true, 512, JSON_THROW_ON_ERROR)['ok'];
    }

    return $results;
}
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('Order management races require the isolated MySQL run.');
    }
});
it('restocks once when two staff cancel the same order concurrently', function () {
    Queue::fake();
    $f = checkoutFixture(1);
    $o = app(PlaceOrder::class)->execute($f['cart'], $f['data']);
    $a = catalogAdmin();
    $input = ['action' => 'cancel', 'order_id' => $o->id, 'admin_id' => $a->id];
    orderManagementRace([$input, $input]);
    expect($f['product']->refresh()->qty)->toBe(1)->and($o->events()->where('type', 'cancelled')->count())->toBe(1);
});
it('caps simultaneous manual payments at the unpaid balance', function () {
    Queue::fake();
    $f = checkoutFixture();
    $o = app(PlaceOrder::class)->execute($f['cart'], $f['data']);
    $a = catalogAdmin();
    $input = ['action' => 'payment', 'order_id' => $o->id, 'admin_id' => $a->id, 'amount' => $o->total];
    $r = orderManagementRace([$input + ['key' => (string) Str::uuid()], $input + ['key' => (string) Str::uuid()]]);
    expect(array_sum($r))->toBe(1)->and($o->refresh()->paid_amount)->toBe($o->total)->and($o->payments()->count())->toBe(1);
});
it('records one refund under simultaneous replay of the same receipt', function () {
    Queue::fake();
    $f = checkoutFixture();
    $o = app(PlaceOrder::class)->execute($f['cart'], $f['data']);
    $a = catalogAdmin();
    $this->actingAs($a, 'admin');
    app(ManageOrder::class)->payment($o, $o->total, 'test', (string) Str::uuid());
    app(ManageOrder::class)->transition($o, 'cancelled');
    $input = ['action' => 'refund', 'order_id' => $o->id, 'admin_id' => $a->id, 'amount' => $o->total, 'key' => (string) Str::uuid()];
    orderManagementRace([$input, $input]);
    expect($o->refunds()->count())->toBe(1)->and($o->refresh()->refunded_amount)->toBe($o->total)->and($f['product']->refresh()->qty)->toBe(10);
});
it('does not overship the final unshipped unit under concurrency', function () {
    Queue::fake();
    $f = checkoutFixture();
    $o = app(PlaceOrder::class)->execute($f['cart'], $f['data']);
    $a = catalogAdmin();
    $this->actingAs($a, 'admin');
    foreach (['confirmed', 'processing', 'packed'] as $status) {
        app(ManageOrder::class)->transition($o, $status);
    }
    $input = ['action' => 'ship', 'order_id' => $o->id, 'admin_id' => $a->id, 'item_id' => $o->items()->first()->id];
    $r = orderManagementRace([$input + ['key' => (string) Str::uuid()], $input + ['key' => (string) Str::uuid()]]);
    expect(array_sum($r))->toBe(1)->and($o->shipments()->count())->toBe(1);
});
it('consumes one email verification code only once across workers', function () {
    $u = User::factory()->create(['email_verified_at' => null]);
    DB::table('otp_codes')->insert(['identifier' => $u->email, 'code_hash' => Hash::make('123456'), 'expires_at' => now()->addMinutes(10), 'attempts' => 0, 'created_at' => now(), 'updated_at' => now()]);
    $input = ['action' => 'otp', 'email' => $u->email];
    $r = orderManagementRace([$input, $input]);
    expect(array_sum($r))->toBe(1)->and($u->refresh()->email_verified_at)->not->toBeNull();
});
it('serializes repeated staff manual-order submission on its stable cart', function () {
    Queue::fake();
    $f = checkoutFixture();
    $a = catalogAdmin();
    $input = ['action' => 'manual', 'admin_id' => $a->id, 'data' => $f['data'] + ['items' => [['product_id' => $f['product']->id, 'qty' => 1]]]];
    orderManagementRace([$input, $input]);
    expect(Order::count())->toBe(1)->and($f['product']->refresh()->qty)->toBe(9);
});
