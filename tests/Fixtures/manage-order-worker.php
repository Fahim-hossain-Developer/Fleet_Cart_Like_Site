<?php

use App\Domain\Customers\Actions\EmailCode;
use App\Domain\Orders\Actions\CreateManualOrder;
use App\Domain\Orders\Actions\ManageOrder;
use App\Domain\Orders\Models\Order;
use App\Models\Admin;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('testing')) {
    exit(2);
}
$d = json_decode($argv[1], true, 512, JSON_THROW_ON_ERROR);
try {
    if (isset($d['admin_id'])) {
        auth('admin')->setUser(Admin::findOrFail($d['admin_id']));
    }
    $o = isset($d['order_id']) ? Order::findOrFail($d['order_id']) : null;
    $a = app(ManageOrder::class);
    switch ($d['action']) {
        case 'cancel':$a->transition($o, 'cancelled');
            break;
        case 'payment':$a->payment($o, $d['amount'], 'TEST-RECEIPT', $d['key']);
            break;
        case 'refund':$a->refund($o, $d['amount'], 'TEST-REFUND', $d['key']);
            break;
        case 'ship':$a->ship($o, [$d['item_id'] => 1], 'Manual', 'TEST-TRACKING', $d['key']);
            break;
        case 'otp':app(EmailCode::class)->consume($d['email'], '123456');
            break;
        case 'manual':app(CreateManualOrder::class)->execute($d['data']);
            break;
    }
    echo json_encode(['ok' => true], JSON_THROW_ON_ERROR);
} catch (ValidationException $e) {
    echo json_encode(['ok' => false, 'validation' => true], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    fwrite(STDERR, get_class($e).': '.$e->getMessage());
    exit(1);
}
