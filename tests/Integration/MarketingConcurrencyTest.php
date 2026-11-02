<?php

use App\Domain\Cart\Actions\CartManager;
use App\Domain\Cart\Models\Cart;
use App\Domain\Catalog\Models\Product;
use App\Domain\Customers\Models\ShoppingListItem;
use App\Domain\Customers\Models\StoreNotification;
use App\Domain\Marketing\Models\CartRecoveryContact;
use App\Domain\Marketing\Models\CartRecoveryMessage;
use App\Domain\Marketing\Models\CartRecoveryRestoration;
use App\Domain\Marketing\Models\FlashAllocation;
use App\Domain\Marketing\Models\FlashSale;
use App\Domain\Marketing\Models\FlashSaleItem;
use App\Domain\Orders\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

beforeEach(function () {
    if (DB::getDriverName() !== 'mysql') {
        $this->markTestSkipped('MySQL marketing races require the isolated disposable database.');
    }
});
function marketingRace(array $inputs, string $fixture = 'marketing-worker.php'): array
{
    $workers = [];
    foreach ($inputs as $data) {
        $p = new Process([PHP_BINARY, base_path('tests/Fixtures/'.$fixture), json_encode($data, JSON_THROW_ON_ERROR)], base_path(), ['APP_ENV' => 'testing', 'MAIL_MAILER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'array']);
        $p->setTimeout(45);
        $p->start();
        $workers[] = $p;
    }
    $results = [];
    foreach ($workers as $p) {
        $p->wait();
        expect($p->isSuccessful())->toBeTrue($p->getErrorOutput());
        $results[] = json_decode($p->getOutput(), true, 512, JSON_THROW_ON_ERROR);
    }

    return $results;
}
it('allocates the last flash unit once while later shoppers receive the recalculated regular price', function () {
    $f = checkoutFixture(10);
    $sale = FlashSale::factory()->create();
    $item = FlashSaleItem::factory()->create(['flash_sale_id' => $sale->id, 'product_id' => $f['product']->id, 'price' => 50000, 'quota' => 1]);
    $second = Cart::factory()->create();
    app(CartManager::class)->add($second, $f['product']->id, null, 1);
    $data = $f['data'];
    $data['idempotency_key'] = (string) Str::uuid();
    marketingRace([['cart_id' => $f['cart']->id, 'data' => $f['data']], ['cart_id' => $second->id, 'data' => $data]], 'place-order-worker.php');
    expect(Order::count())->toBe(2)->and($item->refresh()->sold)->toBe(1)->and(FlashAllocation::count())->toBe(1)->and($f['product']->refresh()->qty)->toBe(8);
    expect(Order::orderBy('subtotal')->pluck('subtotal')->map(fn ($n) => (int) $n)->all())->toBe([50000, 100000]);
});
it('serializes compare capacity under six concurrent additions', function () {
    $u = User::factory()->create();
    $inputs = [];
    foreach (Product::factory()->count(6)->create() as $p) {
        $inputs[] = ['action' => 'compare', 'user_id' => $u->id, 'product_id' => $p->id];
    }
    $result = marketingRace($inputs);
    expect(ShoppingListItem::where('kind', 'compare')->count())->toBe(4)->and(collect($result)->where('ok', true)->count())->toBe(4);
});
it('deduplicates simultaneous real notice sources', function () {
    $u = User::factory()->create();
    $input = ['action' => 'notice', 'user_id' => $u->id];
    marketingRace([$input, $input, $input]);
    expect(StoreNotification::count())->toBe(1);
});
it('restores a recovery capability into the same destination once under concurrent replay', function () {
    $source = Cart::factory()->create();
    $dest = Cart::factory()->create();
    $product = Product::factory()->create(['qty' => 10]);
    $contact = CartRecoveryContact::factory()->create(['cart_id' => $source->id]);
    $token = Str::random(64);
    $message = CartRecoveryMessage::factory()->create(['cart_recovery_contact_id' => $contact->id, 'link_hash' => hash('sha256', $token), 'payload' => ['token' => $token], 'snapshot' => [['product_id' => $product->id, 'variant_id' => null, 'qty' => 2]]]);
    $input = ['action' => 'restore', 'cart_token' => $dest->token, 'token' => $token];
    marketingRace([$input, $input, $input]);
    expect($dest->items()->count())->toBe(1)->and($dest->items()->first()->qty)->toBe(2)->and(CartRecoveryRestoration::count())->toBe(1);
});
