<?php

use App\Domain\Customers\Actions\ProductLists;
use App\Domain\Customers\Actions\PublishNotice;
use App\Domain\Marketing\Actions\RestoreCartRecovery;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('testing')) {
    exit(3);
}
try {
    $d = json_decode($argv[1], true, 512, JSON_THROW_ON_ERROR);
    if (isset($d['user_id'])) {
        auth('web')->login(User::findOrFail($d['user_id']));
    }
    switch ($d['action']) {
        case 'compare':app(ProductLists::class)->set('compare', $d['product_id'], true);
            break;
        case 'notice':app(PublishNotice::class)->execute(auth('web')->user(), 'orders', 'marketing.notice_order', ['number' => 'RACE-1'], '/account', 'race:order:1', false);
            break;
        case 'restore':session(['cart_token' => $d['cart_token']]);
            app(RestoreCartRecovery::class)->execute($d['token']);
            break;
        default:throw new RuntimeException('Unknown action');
    }
    echo json_encode(['ok' => true]);
} catch (ValidationException $e) {
    echo json_encode(['ok' => false]);
} catch (Throwable $e) {
    fwrite(STDERR, get_class($e).': worker failed');
    exit(1);
}
