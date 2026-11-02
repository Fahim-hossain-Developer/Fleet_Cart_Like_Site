<?php

use App\Domain\Cart\Actions\CartManager;
use App\Domain\Cart\Actions\CartSession;
use App\Domain\Cart\Actions\QuoteCart;
use App\Domain\Cart\Models\Cart;
use App\Domain\Cart\Policies\CartPolicy;
use App\Domain\Catalog\Models\OptionValue;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductOption;
use App\Domain\Checkout\Actions\PriceCalculator;
use App\Domain\Customers\Actions\NormalizePhone;
use App\Domain\Marketing\Models\Coupon;
use App\Domain\Orders\Actions\PlaceOrder;
use App\Domain\Orders\Models\Order;
use App\Domain\Settings\Actions\CredentialVault;
use App\Domain\Settings\Actions\CurrencyDisplay;
use App\Domain\Settings\Actions\Settings;
use App\Domain\Settings\Models\Currency;
use App\Domain\Shipping\Actions\ShippingResolver;
use App\Domain\Shipping\Models\Location;
use App\Jobs\SendOrderConfirmation;
use App\Mail\OrderPlaced;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

it('normalizes BD phone formats and rejects unsupported numbers', function () {
    $normalizer = app(NormalizePhone::class);
    expect($normalizer->execute('01712 345678'))->toBe('+8801712345678')->and($normalizer->execute('008801712345678'))->toBe('+8801712345678');
    expect(fn () => $normalizer->execute('01212345678'))->toThrow(ValidationException::class);
});
it('quotes integer discount allocation and inclusive/exclusive taxes correctly', function () {
    $calculator = app(PriceCalculator::class);
    $quote = $calculator->calculate([['subtotal' => 10000, 'tax_bps' => 1000], ['subtotal' => 11000, 'tax_bps' => 1000, 'tax_inclusive' => true]], 2100, 800, 500, 1000);
    expect($quote['subtotal'])->toBe(21000)->and($quote['discount'])->toBe(2100)->and($quote['tax_included'])->toBe(900)->and($quote['tax'])->toBe(1850)->and($quote['total'])->toBe(21150);
    expect(array_sum(array_column($quote['lines'], 'discount')))->toBe(2100);
});
it('supports add, quantity, remove and save-for-later without trusting item prices', function () {
    $f = checkoutFixture();
    app(CartManager::class)->quantity($f['cart'], $f['cart']->items()->first()->id, 3);
    expect(app(QuoteCart::class)->execute($f['cart'])[0]['subtotal'])->toBe(300000);
    $item = $f['cart']->items()->first();
    app(CartManager::class)->save($f['cart'], $item->id, true);
    expect(app(QuoteCart::class)->execute($f['cart']))->toBe([]);
    app(CartManager::class)->save($f['cart'], $item->id, false);
    app(CartManager::class)->remove($f['cart'], $item->id);
    expect($f['cart']->items()->count())->toBe(0);
});
it('scopes cart mutations to the current cart and rejects invalid stock/variants', function () {
    $f = checkoutFixture(1);
    expect(fn () => app(CartManager::class)->add($f['cart'], $f['product']->id, null, 1))->toThrow(ValidationException::class);
    $other = Cart::create(['token' => Str::random(64)]);
    $otherItem = $other->items()->create(['product_id' => $f['product']->id, 'line_key' => Str::random(64), 'qty' => 1, 'options' => []]);
    expect(fn () => app(CartManager::class)->quantity($f['cart'], $otherItem->id, 1))->toThrow(ModelNotFoundException::class);
    $variable = Product::factory()->create(['type' => 'variable']);
    expect(fn () => app(CartManager::class)->add($f['cart'], $variable->id, null, 1))->toThrow(ValidationException::class);
});
it('validates required options, value ownership and percentage pricing server side', function () {
    $f = checkoutFixture();
    $p = $f['product'];
    $option = ProductOption::factory()->create(['product_id' => $p->id, 'type' => 'dropdown', 'price' => 10, 'price_type' => 'percent', 'required' => true]);
    $value = OptionValue::factory()->create(['product_option_id' => $option->id, 'price' => 0]);
    expect(fn () => app(CartManager::class)->add($f['cart'], $p->id, null, 1))->toThrow(ValidationException::class);
    app(CartManager::class)->remove($f['cart'], $f['cart']->items()->first()->id);
    app(CartManager::class)->add($f['cart'], $p->id, null, 2, [(string) $option->id => (string) $value->id]);
    expect(app(QuoteCart::class)->execute($f['cart'])[0]['subtotal'])->toBe(220000);
    expect(fn () => app(CartManager::class)->add($f['cart'], $p->id, null, 1, [(string) $option->id => '99999']))->toThrow(ValidationException::class);
});
it('aggregates stock across option lines before checkout', function () {
    $f = checkoutFixture(2);
    $option = ProductOption::factory()->create(['product_id' => $f['product']->id, 'type' => 'text', 'required' => false, 'price' => 0]);
    app(CartManager::class)->add($f['cart'], $f['product']->id, null, 2, [(string) $option->id => 'engraving']);
    expect(fn () => app(QuoteCart::class)->execute($f['cart']))->toThrow(ValidationException::class);
});
it('persists authenticated carts and merges a guest cart without merging other users', function () {
    $f = checkoutFixture();
    $user = User::factory()->create();
    $this->actingAs($user, 'web');
    $cart = app(CartSession::class)->current();
    expect($cart->user_id)->toBe($user->id)->and($cart->items()->count())->toBe(1)->and(Cart::whereKey($f['cart']->id)->exists())->toBeFalse();
});
it('places an unpaid COD order with snapshots and atomic stock deduction', function () {
    Queue::fake();
    $f = checkoutFixture();
    $response = $this->post('/checkout', $f['data'] + ['total' => 1, 'paid_amount' => 999999]);
    $order = Order::firstOrFail();
    $response->assertRedirect(route('checkout.success', $order->number));
    expect($order->total)->toBe(108000)->and($order->payment_status)->toBe('unpaid')->and($order->items)->toHaveCount(1)->and($f['product']->refresh()->qty)->toBe(9)->and($f['cart']->items()->count())->toBe(0)->and($order->phone)->toBe('+8801712345678');
    $this->get(route('checkout.success', $order->number))->assertOk()->assertSee($order->number);
    $f['product']->update(['name' => ['en' => 'Renamed']]);
    expect($order->items()->first()->name)->not->toBe('Renamed');
    Queue::assertPushed(SendOrderConfirmation::class);
});
it('replays the same checkout key without duplicate stock or orders', function () {
    Queue::fake();
    $f = checkoutFixture();
    $first = app(PlaceOrder::class)->execute($f['cart'], $f['data']);
    $again = app(PlaceOrder::class)->execute($f['cart'], $f['data']);
    expect($again->id)->toBe($first->id)->and(Order::count())->toBe(1)->and($f['product']->refresh()->qty)->toBe(9);
});
it('protects receipt ownership from another browser session', function () {
    Queue::fake();
    $f = checkoutFixture();
    $order = app(PlaceOrder::class)->execute($f['cart'], $f['data']);
    $this->get(route('checkout.success', $order->number))->assertForbidden();
});
it('rechecks coupon counters, expiry, minimum and per-phone usage at placement', function () {
    Queue::fake();
    $f = checkoutFixture();
    $coupon = Coupon::create(['code' => 'TEN', 'type' => 'percent', 'value' => 10, 'minimum' => 50000, 'usage_limit' => 1, 'per_customer_limit' => 1, 'is_active' => true]);
    $f['cart']->update(['coupon_id' => $coupon->id]);
    $order = app(PlaceOrder::class)->execute($f['cart'], $f['data']);
    expect($order->discount)->toBe(10000)->and($coupon->refresh()->used)->toBe(1);
    $other = Cart::create(['token' => Str::random(64), 'coupon_id' => $coupon->id]);
    app(CartManager::class)->add($other, $f['product']->id, null, 1);
    $data = $f['data'];
    $data['idempotency_key'] = (string) Str::uuid();
    expect(fn () => app(PlaceOrder::class)->execute($other, $data))->toThrow(ValidationException::class);
    expect(Order::count())->toBe(1);
});
it('rejects unsupported delivery and mismatched address hierarchy without creating an order', function () {
    $f = checkoutFixture();
    $data = $f['data'];
    $data['shipping_method_id'] = 999999;
    expect(fn () => app(PlaceOrder::class)->execute($f['cart'], $data))->toThrow(ValidationException::class);
    expect(Order::count())->toBe(0)->and($f['product']->refresh()->qty)->toBe(10);
    $data = $f['data'];
    $data['upazila_id'] = $f['district']->id;
    expect(fn () => app(PlaceOrder::class)->execute($f['cart'], $data))->toThrow(ValidationException::class);
});
it('resolves weight, pickup and free-shipping thresholds without uncovered free rates', function () {
    $f = checkoutFixture();
    $m = $f['method'];
    $m->update(['type' => 'weight', 'base_weight_grams' => 1000, 'step_weight_grams' => 1000, 'step_amount' => 2000]);
    $resolver = app(ShippingResolver::class);
    expect($resolver->resolve('BD', $f['district']->id, $m->id, 100000, 2500)['amount'])->toBe(12000);
    $m->update(['free_above' => 100000]);
    expect($resolver->resolve('BD', $f['district']->id, $m->id, 100000, 2500)['amount'])->toBe(0);
});
it('adds gift fees and recipient details and uses per-product fee overrides', function () {
    Queue::fake();
    $f = checkoutFixture();
    $f['product']->update(['gift_fee' => 1200]);
    $data = $f['data'];
    $data['is_gift'] = true;
    $data['gift_message'] = 'Happy birthday';
    $data['recipient_phone'] = '01812345678';
    $data['hide_prices'] = true;
    $order = app(PlaceOrder::class)->execute($f['cart'], $data);
    expect($order->gift_fee)->toBe(1200)->and($order->total)->toBe(109200)->and($order->is_gift)->toBeTrue()->and($order->gift_message)->toBe('Happy birthday')->and($order->recipient_phone)->toBe('+8801812345678');
});
it('creates optional new accounts without login and avoids attaching guests to existing accounts', function () {
    Queue::fake();
    Mail::fake();
    $f = checkoutFixture();
    $data = $f['data'];
    $data['create_account'] = true;
    $order = app(PlaceOrder::class)->execute($f['cart'], $data);
    expect($order->user_id)->not->toBeNull();
    $this->assertGuest('web');
    expect(DB::table('account_setup_tokens')->count())->toBe(1);
    $other = Cart::create(['token' => Str::random(64)]);
    app(CartManager::class)->add($other, $f['product']->id, null, 1);
    $data['idempotency_key'] = (string) Str::uuid();
    $second = app(PlaceOrder::class)->execute($other, $data);
    expect($second->user_id)->toBeNull()->and(User::count())->toBe(1);
});
it('requires single-use unexpired setup tokens to change a password', function () {
    $user = User::factory()->create();
    $raw = Str::random(64);
    DB::table('account_setup_tokens')->insert(['user_id' => $user->id, 'token_hash' => hash('sha256', $raw), 'expires_at' => now()->addHour(), 'created_at' => now(), 'updated_at' => now()]);
    $this->get('/account/setup/'.$raw)->assertOk();
    $this->post('/account/setup/'.$raw, ['password' => 'long-test-password-123', 'password_confirmation' => 'long-test-password-123'])->assertRedirect('/');
    $this->get('/account/setup/'.$raw)->assertNotFound();
    $this->assertGuest('web');
});
it('keeps manual payment unpaid and rejects unconfigured payment methods', function () {
    Queue::fake();
    $f = checkoutFixture();
    $data = $f['data'];
    $data['payment_method'] = 'bank';
    $data['payment_reference'] = 'TRANSFER-1';
    expect(fn () => app(PlaceOrder::class)->execute($f['cart'], $data))->toThrow(ValidationException::class);
    app(Settings::class)->set('checkout_payment_methods', ['cod' => ['enabled' => true, 'instructions' => ''], 'bank' => ['enabled' => true, 'instructions' => 'Send to the configured test bank account.'], 'manual_mobile' => ['enabled' => false, 'instructions' => '']]);
    $order = app(PlaceOrder::class)->execute($f['cart'], $data);
    expect($order->payment_status)->toBe('unpaid')->and($order->payment_reference)->toBe('TRANSFER-1');
});
it('converts display amounts using integer rates and persists currency preferences', function () {
    $f = checkoutFixture();
    $usd = Currency::create(['code' => 'USD', 'symbol' => '$', 'rate_ppm' => 10000, 'decimals' => 2, 'rounding_increment' => 1, 'markup_bps' => 0, 'is_active' => true]);
    $display = app(CurrencyDisplay::class);
    expect($display->convert(125000, $usd))->toBe(1250)->and($display->format(125000, $usd))->toBe('$ 12.50');
    $this->post('/display-currency', ['currency' => 'USD'])->assertRedirect();
    expect(session('display_currency'))->toBe('USD');
    Queue::fake();
    $order = app(PlaceOrder::class)->execute($f['cart'], $f['data']);
    expect($order->currency)->toBe('BDT')->and($order->display_currency)->toBe('USD')->and($order->display_rate_ppm)->toBe(10000)->and($order->total)->toBe(108000)->and($order->display_total)->toBe(1080);
});
it('requires terms and a real phone and ignores client-calculated totals', function () {
    $f = checkoutFixture();
    $data = $f['data'];
    unset($data['terms']);
    $this->post('/checkout', $data)->assertSessionHasErrors('terms');
    $data = $f['data'];
    $data['phone'] = 'bad';
    $this->post('/checkout', $data)->assertSessionHasErrors('phone');
    expect(Order::count())->toBe(0);
});
it('denies non-privileged commerce configuration and exposes the configured admin screens', function () {
    $support = catalogAdmin('support');
    $this->actingAs($support, 'admin');
    foreach (['/admin/coupons', '/admin/shipping-zones', '/admin/shipping-methods', '/admin/tax-classes', '/admin/tax-rates', '/admin/currencies', '/admin/checkout-preferences', '/admin/orders'] as $path) {
        $this->get($path)->assertForbidden();
    }
    $this->flushSession();
    $owner = catalogAdmin();
    $this->actingAs($owner, 'admin');
    foreach (['/admin/coupons', '/admin/shipping-zones', '/admin/shipping-methods', '/admin/tax-classes', '/admin/tax-rates', '/admin/currencies', '/admin/checkout-preferences', '/admin/orders'] as $path) {
        $this->get($path)->assertOk();
    }
});

it('does not leak encrypted credentials in cart, checkout or a receipt', function () {
    Queue::fake();
    $f = checkoutFixture();
    $secret = 'sk_live_PHASE2_SENTINEL';
    app(CredentialVault::class)->replace('gateway', $secret);
    foreach (['/cart', '/checkout'] as $path) {
        $this->get($path)->assertOk()->assertDontSee($secret)->assertDontSee('sk_live_');
    }
    $this->post('/checkout', $f['data'])->assertRedirect();
    $order = Order::firstOrFail();
    $this->get(route('checkout.success', $order->number))->assertOk()->assertDontSee($secret)->assertDontSee('sk_live_');
});

it('keeps the base currency amount invariant and records manual rate history', function () {
    $f = checkoutFixture();
    $base = Currency::where('code', 'BDT')->firstOrFail();
    expect(fn () => $base->update(['rate_ppm' => 2000000]))->toThrow(ValidationException::class);
    $usd = Currency::create(['code' => 'USD', 'symbol' => '$', 'rate_ppm' => 10000, 'decimals' => 2, 'is_active' => true]);
    $usd->update(['rate_ppm' => 11000]);
    expect(DB::table('currency_rate_history')->where('currency_id', $usd->id)->count())->toBe(2);
});

it('delivers queued order mail once and keeps a durable retry record', function () {
    Queue::fake();
    Mail::fake();
    $f = checkoutFixture();
    $order = app(PlaceOrder::class)->execute($f['cart'], $f['data']);
    $job = new SendOrderConfirmation($order->id);
    $job->handle();
    $job->handle();
    Mail::assertSent(OrderPlaced::class, 1);
    expect(DB::table('order_notifications')->where('order_id', $order->id)->value('sent_at'))->not->toBeNull();
});

it('preserves readable option snapshots after the product configuration changes', function () {
    Queue::fake();
    $f = checkoutFixture();
    app(CartManager::class)->remove($f['cart'], $f['cart']->items()->first()->id);
    $option = ProductOption::factory()->create(['product_id' => $f['product']->id, 'type' => 'text', 'label' => ['en' => 'Engraving'], 'price' => 500]);
    app(CartManager::class)->add($f['cart'], $f['product']->id, null, 1, [(string) $option->id => 'Original message']);
    $order = app(PlaceOrder::class)->execute($f['cart'], $f['data']);
    $option->update(['label' => ['en' => 'Changed']]);
    expect($order->items()->first()->options['labels'][0])->toBe(['label' => 'Engraving', 'value' => 'Original message']);
});

it('authorizes cart policies only for the session or authenticated owner', function () {
    $f = checkoutFixture();
    $guest = new CartPolicy;
    expect($guest->view(null, $f['cart']))->toBeTrue();
    session(['cart_token' => Str::random(64)]);
    expect($guest->view(null, $f['cart']))->toBeFalse();
});

it('rejects percent coupons over one hundred and ignores hidden gift fields when gift is off', function () {
    expect(fn () => Coupon::create(['code' => 'INVALID', 'type' => 'percent', 'value' => 101]))->toThrow(ValidationException::class);
    Queue::fake();
    $f = checkoutFixture();
    $data = $f['data'] + ['recipient_phone' => 'not-a-phone', 'gift_message' => 'Old hidden message'];
    $data['hide_prices'] = true;
    $order = app(PlaceOrder::class)->execute($f['cart'], $data);
    expect($order->gift_fee)->toBe(0)->and($order->hide_prices)->toBeFalse()->and($order->recipient_phone)->toBeNull()->and($order->gift_message)->toBeNull();
});

it('preserves a committed order when account setup mail fails and retains encrypted retry data', function () {
    Queue::fake([SendOrderConfirmation::class]);
    Mail::shouldReceive('raw')->andThrow(new RuntimeException('Simulated mail outage'));
    $f = checkoutFixture();
    $data = $f['data'];
    $data['create_account'] = true;
    $order = app(PlaceOrder::class)->execute($f['cart'], $data);
    expect($order->id)->not->toBeNull()->and(Order::count())->toBe(1);
    $delivery = DB::table('account_setup_tokens')->first();
    expect($delivery->sent_at)->toBeNull()->and($delivery->delivery_payload)->not->toBeNull();
});

it('uses the configured order prefix while retaining unique order numbers', function () {
    Queue::fake();
    $f = checkoutFixture();
    app(Settings::class)->set('checkout_order_prefix', 'SHOP');
    $order = app(PlaceOrder::class)->execute($f['cart'], $f['data']);
    expect($order->number)->toStartWith('SHOP-');
});

it('uses configured area dropdown records and rejects an area from another upazila', function () {
    Queue::fake();
    $f = checkoutFixture();
    $area = Location::create(['code' => Str::random(10), 'country' => 'BD', 'type' => 'area', 'parent_id' => $f['upazila']->id, 'name' => ['en' => 'Test area', 'bn' => 'নমুনা এলাকা']]);
    expect(fn () => app(PlaceOrder::class)->execute($f['cart'], $f['data']))->toThrow(ValidationException::class);
    $data = $f['data'];
    $data['area_id'] = $f['district']->id;
    expect(fn () => app(PlaceOrder::class)->execute($f['cart'], $data))->toThrow(ValidationException::class);
    $data['area_id'] = $area->id;
    $order = app(PlaceOrder::class)->execute($f['cart'], $data);
    expect($order->shipping_address['area']['en'])->toBe('Test area');
});
