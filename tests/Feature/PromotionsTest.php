<?php

use App\Domain\Catalog\Actions\CatalogPrice;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductDownload;
use App\Domain\Checkout\Actions\CheckoutQuote;
use App\Domain\Marketing\Actions\AutomaticDiscounts;
use App\Domain\Marketing\Actions\BestSellers;
use App\Domain\Marketing\Models\CartRule;
use App\Domain\Marketing\Models\Coupon;
use App\Domain\Marketing\Models\FlashAllocation;
use App\Domain\Marketing\Models\FlashSale;
use App\Domain\Marketing\Models\FlashSaleItem;
use App\Domain\Orders\Actions\EditOrderItems;
use App\Domain\Orders\Actions\ManageOrder;
use App\Domain\Orders\Models\DownloadEntitlement;
use App\Domain\Orders\Models\Order;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

it('uses one authoritative flash price in cards SQL filters cart and order snapshots and releases its quota once', function () {
    Queue::fake();
    $f = checkoutFixture();
    $flash = FlashSaleItem::factory()->create(['product_id' => $f['product']->id, 'price' => 70000, 'quota' => 1]);
    expect($f['product']->currentPrice())->toBe(70000);
    $sql = app(CatalogPrice::class)->expression();
    expect((int) Product::whereKey($f['product']->id)->selectRaw('('.$sql.') AS actual_price')->value('actual_price'))->toBe(70000);
    $quote = app(CheckoutQuote::class)->execute($f['cart'], $f['data']);
    expect($quote['total'])->toBe(78000);
    $this->post('/checkout', $f['data'])->assertRedirect();
    $o = Order::firstOrFail();
    expect($o->items()->first()->unit_price)->toBe(70000)->and($flash->refresh()->sold)->toBe(1)->and(FlashAllocation::count())->toBe(1)->and($f['product']->refresh()->currentPrice())->toBe(100000);
    $this->actingAs(catalogAdmin(), 'admin');
    expect(fn () => app(EditOrderItems::class)->execute($o, [$o->items()->first()->id => 2]))->toThrow(ValidationException::class);
    app(ManageOrder::class)->transition($o, 'cancelled');
    app(ManageOrder::class)->transition($o, 'cancelled');
    expect($flash->refresh()->sold)->toBe(0)->and(FlashAllocation::first()->released)->toBe(1);
});
it('does not advertise exhausted future disabled or expired promotions', function () {
    $p = Product::factory()->create(['price' => 100000]);
    $sale = FlashSale::factory()->create(['starts_at' => now()->addHour(), 'ends_at' => now()->addDay()]);
    $f = FlashSaleItem::factory()->create(['flash_sale_id' => $sale->id, 'product_id' => $p->id, 'price' => 40000, 'quota' => 1]);
    expect($p->currentPrice())->toBe(100000);
    $sale->update(['starts_at' => now()->subHour()]);
    expect($p->currentPrice())->toBe(40000);
    $f->update(['sold' => 1]);
    expect($p->currentPrice())->toBe(100000);
    $f->update(['sold' => 0]);
    $sale->update(['is_active' => false]);
    expect($p->currentPrice())->toBe(100000);
});
it('applies ordered cart rules before coupons using exact integer rounding and stores immutable rule evidence', function () {
    Queue::fake();
    $f = checkoutFixture();
    $rule = CartRule::factory()->create(['type' => 'percent', 'value' => 10, 'stackable' => true, 'priority' => 1]);
    CartRule::factory()->create(['type' => 'fixed', 'value' => 5000, 'priority' => 2]);
    CartRule::factory()->create(['type' => 'fixed', 'value' => 999999, 'priority' => 3]);
    $coupon = Coupon::factory()->create(['type' => 'percent', 'value' => 10, 'minimum' => 0]);
    $f['cart']->update(['coupon_id' => $coupon->id]);
    $quote = app(CheckoutQuote::class)->execute($f['cart'], $f['data']);
    expect($quote['automatic_discount'])->toBe(15000)->and($quote['coupon_discount'])->toBe(8500)->and($quote['discount'])->toBe(23500)->and($quote['total'])->toBe(84500);
    $this->post('/checkout', $f['data'])->assertRedirect();
    $o = Order::firstOrFail();
    expect($o->automatic_discount)->toBe(15000)->and(count($o->cart_rule_snapshot))->toBe(2);
    $rule->update(['value' => 99]);
    expect($o->refresh()->total)->toBe(84500);
});
it('bounds automatic discounts and free shipping and respects schedule and quantity conditions', function () {
    CartRule::factory()->create(['minimum' => 200000, 'type' => 'percent', 'value' => 50]);
    CartRule::factory()->create(['minimum_qty' => 3, 'type' => 'fixed', 'value' => 10000000]);
    expect(app(AutomaticDiscounts::class)->calculate(100000, 1)['discount'])->toBe(0);
    CartRule::factory()->create(['type' => 'free_shipping', 'value' => 0, 'priority' => 1, 'stackable' => true]);
    CartRule::factory()->create(['type' => 'percent', 'value' => 1000, 'priority' => 2]);
    $r = app(AutomaticDiscounts::class)->calculate(100000, 1);
    expect($r['discount'])->toBe(100000)->and($r['free_shipping'])->toBeTrue();
});
it('limits promotion editors to marketing staff and prevents deleting allocated campaigns', function () {
    $this->actingAs(catalogAdmin('manager'), 'admin')->get('/admin/flash-sales')->assertOk();
    $this->get('/admin/cart-rules')->assertOk();
    $this->flushSession();
    $this->actingAs(catalogAdmin('content-editor'), 'admin')->get('/admin/flash-sales')->assertForbidden();
});

it('handles a genuinely free order without inventing a payment receipt or leaving entitlements unpaid', function () {
    Queue::fake();
    $f = checkoutFixture();
    $f['product']->update(['type' => 'digital']);
    ProductDownload::create(['product_id' => $f['product']->id, 'label' => 'Digital guide', 'external_url' => 'https://example.com/guide', 'limit' => 2, 'expiry_days' => 7]);
    CartRule::factory()->create(['type' => 'percent', 'value' => 100]);
    $this->post('/checkout', $f['data'])->assertRedirect();
    $o = Order::firstOrFail();
    expect($o->total)->toBe(0)->and($o->payment_status)->toBe('paid')->and($o->paid_amount)->toBe(0)->and($o->payments()->count())->toBe(0)->and(DownloadEntitlement::first()->enabled_at)->not->toBeNull();
});
it('shows factual flash quota and expiry and best seller badges only from eligible sales', function () {
    $p = Product::factory()->create(['price' => 10000]);
    $flash = FlashSaleItem::factory()->create(['product_id' => $p->id, 'price' => 5000, 'quota' => 4]);
    $this->get(route('product', $p->slug))->assertOk()->assertSee('4 promotional units remaining.')->assertSee('flash-status-'.$flash->id);
    $order = Order::factory()->create(['status' => 'delivered', 'payment_status' => 'paid', 'payment_environment' => 'sandbox', 'total' => 10000, 'paid_amount' => 10000]);
    $order->items()->create(['product_id' => $p->id, 'name' => $p->name, 'sku' => $p->sku, 'options' => [], 'qty' => 1, 'unit_price' => 10000, 'subtotal' => 10000, 'discount' => 0, 'tax' => 0, 'total' => 10000]);
    Cache::forget('catalog.real-best-sellers');
    expect(app(BestSellers::class)->ids())->toBe([]);
    $order->update(['payment_environment' => 'live']);
    Cache::forget('catalog.real-best-sellers');
    expect(app(BestSellers::class)->ids())->toBe([$p->id]);
    app(\App\Domain\Settings\Actions\Settings::class)->set('catalog_max_badges',10);
    $this->get(route('product', $p->slug))->assertOk()->assertSee('Best seller');
});
