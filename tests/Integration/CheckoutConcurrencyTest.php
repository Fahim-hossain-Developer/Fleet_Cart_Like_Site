<?php

use App\Domain\Cart\Actions\CartManager;
use App\Domain\Cart\Models\Cart;
use App\Domain\Marketing\Models\Coupon;
use App\Domain\Orders\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

it('prevents simultaneous purchase of the last item and replays one request idempotently', function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('MySQL row-lock concurrency requires the isolated MySQL test run.');
    }
    $f = checkoutFixture(1);
    $second = Cart::create(['token' => Str::random(64)]);
    app(CartManager::class)->add($second, $f['product']->id, null, 1);
    $other = $f['data'];
    $other['idempotency_key'] = (string) Str::uuid();
    $workers = [];
    foreach ([[$f['cart'], $f['data']], [$second, $other]] as [$cart,$data]) {
        $p = new Process([PHP_BINARY, base_path('tests/Fixtures/place-order-worker.php'), json_encode(['cart_id' => $cart->id, 'data' => $data], JSON_THROW_ON_ERROR)], base_path(), ['APP_ENV' => 'testing', 'MAIL_MAILER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'array']);
        $p->setTimeout(30);
        $p->start();
        $workers[] = $p;
    }
    $placed = 0;
    foreach ($workers as $worker) {
        $worker->wait();
        expect($worker->isSuccessful())->toBeTrue($worker->getErrorOutput());
        $result = json_decode($worker->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        $placed += (int) $result['placed'];
    }
    expect($placed)->toBe(1)->and(Order::count())->toBe(1)->and($f['product']->refresh()->qty)->toBe(0);
});
it('atomically enforces a one-use coupon across separate carts', function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('Coupon row-lock concurrency requires the isolated MySQL test run.');
    }
    $f = checkoutFixture(10);
    $coupon = Coupon::create(['code' => 'ONLYONCE', 'type' => 'fixed', 'value' => 5000, 'usage_limit' => 1, 'is_active' => true]);
    $f['cart']->update(['coupon_id' => $coupon->id]);
    $second = Cart::create(['token' => Str::random(64), 'coupon_id' => $coupon->id]);
    app(CartManager::class)->add($second, $f['product']->id, null, 1);
    $other = $f['data'];
    $other['idempotency_key'] = (string) Str::uuid();
    $other['phone'] = '01812345678';
    $workers = [];
    foreach ([[$f['cart'], $f['data']], [$second, $other]] as [$cart,$data]) {
        $p = new Process([PHP_BINARY, base_path('tests/Fixtures/place-order-worker.php'), json_encode(['cart_id' => $cart->id, 'data' => $data], JSON_THROW_ON_ERROR)], base_path(), ['APP_ENV' => 'testing', 'MAIL_MAILER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'array']);
        $p->setTimeout(30);
        $p->start();
        $workers[] = $p;
    }
    foreach ($workers as $worker) {
        $worker->wait();
        expect($worker->isSuccessful())->toBeTrue($worker->getErrorOutput());
    }
    expect(Order::count())->toBe(1)->and($coupon->refresh()->used)->toBe(1)->and(DB::table('coupon_usages')->count())->toBe(1);
});

it('serializes simultaneous duplicate submissions into one order', function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('Submission row-lock concurrency requires the isolated MySQL test run.');
    }
    $f = checkoutFixture(3);
    $workers = [];
    for ($i = 0; $i < 2; $i++) {
        $p = new Process([PHP_BINARY, base_path('tests/Fixtures/place-order-worker.php'), json_encode(['cart_id' => $f['cart']->id, 'data' => $f['data']], JSON_THROW_ON_ERROR)], base_path(), ['APP_ENV' => 'testing', 'MAIL_MAILER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'array']);
        $p->setTimeout(30);
        $p->start();
        $workers[] = $p;
    }
    $ids = [];
    foreach ($workers as $worker) {
        $worker->wait();
        expect($worker->isSuccessful())->toBeTrue($worker->getErrorOutput());
        $ids[] = json_decode($worker->getOutput(), true, 512, JSON_THROW_ON_ERROR)['id'];
    }
    expect(array_unique($ids))->toHaveCount(1)->and(Order::count())->toBe(1)->and($f['product']->refresh()->qty)->toBe(2)->and(DB::table('order_notifications')->count())->toBe(1);
});
