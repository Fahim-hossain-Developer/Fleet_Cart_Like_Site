<?php

use App\Domain\Customers\Actions\AdminCode;
use App\Domain\Customers\Actions\PhoneCode;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Actions\PaymentEngine;
use App\Domain\Payments\Actions\RefundEngine;
use App\Domain\Payments\Models\GatewayAttempt;
use App\Domain\Shipping\Actions\CourierEngine;
use App\Domain\Shipping\Models\CourierBooking;
use App\Models\Admin;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('testing')) {
    exit(2);
}
$d = json_decode($argv[1], true, 512, JSON_THROW_ON_ERROR);
Queue::fake();
try {
    if (isset($d['admin_id'])) {
        auth('admin')->setUser(Admin::findOrFail($d['admin_id']));
    }
    if (isset($d['user_id'])) {
        auth('web')->setUser(User::findOrFail($d['user_id']));
    }
    switch ($d['action']) {
        case 'start':app(PaymentEngine::class)->start(Order::findOrFail($d['order_id']), 'fake', $d['key']);
            break;
        case 'verify':app(PaymentEngine::class)->verify(GatewayAttempt::findOrFail($d['attempt_id']));
            break;
        case 'refund':app(RefundEngine::class)->start(GatewayAttempt::findOrFail($d['attempt_id']), $d['amount'], 'Test refund', $d['key']);
            break;
        case 'book':app(CourierEngine::class)->book(Order::findOrFail($d['order_id']), 'courier_fake', $d['key']);
            break;
        case 'collect':app(CourierEngine::class)->receipt(CourierBooking::findOrFail($d['booking_id']), $d['amount'], $d['key'], $d['key']);
            break;
        case 'phone':app(PhoneCode::class)->consume($d['phone'], '123456');
            break;
        case 'admin':app(AdminCode::class)->consume($d['uuid'], '123456');
            break;
    }
    echo json_encode(['ok' => true]);
} catch (ValidationException $e) {
    echo json_encode(['ok' => false]);
} catch (Throwable $e) {
    fwrite(STDERR, get_class($e).': '.$e->getMessage());
    exit(1);
}
