<?php

use App\Domain\Cart\Models\Cart;
use App\Domain\Orders\Actions\PlaceOrder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('testing')) {
    exit(3);
}
try {
    $payload = json_decode($argv[1], true, 512, JSON_THROW_ON_ERROR);
    $cart = Cart::findOrFail($payload['cart_id']);
    $order = app(PlaceOrder::class)->execute($cart, $payload['data']);
    echo json_encode(['placed' => true, 'id' => $order->id], JSON_THROW_ON_ERROR);
} catch (ValidationException $e) {
    echo json_encode(['placed' => false, 'validation' => true], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    fwrite(STDERR, get_class($e).': '.$e->getMessage());
    exit(1);
}
