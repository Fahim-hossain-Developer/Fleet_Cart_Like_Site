<?php

use App\Domain\Cart\Actions\CartSession;
use App\Domain\Checkout\Landing\CheckoutPhoneProof;
use App\Domain\Checkout\Landing\LandingCart;
use App\Domain\Checkout\Landing\LandingCheckout;
use App\Domain\Checkout\Landing\LandingOffers;
use App\Domain\Checkout\Models\CheckoutPhoneChallenge;
use App\Domain\Content\Landing\LandingRules;
use App\Domain\Content\Landing\LandingTemplates;
use App\Domain\Content\Models\LandingPage;
use App\Domain\Content\Models\LandingTemplate;
use App\Domain\Content\Models\ProductPackage;
use App\Domain\Customers\Actions\Sms;
use App\Domain\Customers\Models\SmsDelivery;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Actions\RecordPaymentReceipt;
use App\Domain\Settings\Actions\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(fn () => Queue::fake());
function landingFixture(array $config = []): array
{
    $f = checkoutFixture(20);
    $page = LandingPage::factory()->create(['product_id' => $f['product']->id, 'form_config' => array_replace(app(LandingRules::class)->formDefaults(), $config)]);
    $package = ProductPackage::factory()->create(['landing_page_id' => $page->id, 'product_id' => $f['product']->id, 'qty' => 3, 'price' => 125000]);
    $data = $f['data'] + ['package_id' => $package->id, 'units' => 1];

    return $f + compact('page', 'package', 'data');
}
// array union keeps fixture data; explicitly build the landing payload after fixture creation.
function landingPayload(array $f): array
{
    return array_replace($f['data'], ['package_id' => $f['package']->id, 'units' => 1]);
}
function landingQuotedPayload(array $f, array $overrides = []): array
{
    $d = array_replace(landingPayload($f), $overrides);
    $quote = app(LandingCheckout::class)->quote($f['page'], $d);
    $d['quote_hash'] = $quote['quote_hash'];

    return $d;
}
it('uses exact server package totals and physical inventory without replacing the ordinary cart', function () {
    $f = landingFixture();
    $data = landingQuotedPayload($f, ['total' => 1, 'unit_price' => 1]);
    $order = app(LandingCheckout::class)->place($f['page'], $data);
    $item = $order->items()->firstOrFail();
    expect($order->subtotal)->toBe(125000)->and($order->total)->toBe(133000)->and($item->qty)->toBe(3)->and($item->unit_price)->toBe(41666)->and($item->package_snapshot['package_price'])->toBe(125000)->and($f['product']->refresh()->qty)->toBe(17);
    expect(app(CartSession::class)->current()->id)->toBe($f['cart']->id)->and($f['cart']->items()->first()->qty)->toBe(1);
    expect($order->checkout_meta)->toBeArray()->and(DB::table('orders')->where('id', $order->id)->value('checkout_meta'))->not->toContain('district_id');
});
it('rejects a stale quote before recording an order and enforces exact shipping coverage', function () {
    $f = landingFixture();
    $data = landingQuotedPayload($f);
    $f['package']->update(['price' => 130000]);
    expect(fn () => app(LandingCheckout::class)->place($f['page'], $data))->toThrow(ValidationException::class);
    expect(Order::count())->toBe(0);
    $data['shipping_method_id'] = 999999;
    expect(fn () => app(LandingCheckout::class)->quote($f['page'], $data))->toThrow(ValidationException::class);
});
it('replays one placement once and blocks another session without revealing the existing order', function () {
    $f = landingFixture();
    $data = landingQuotedPayload($f);
    $order = app(LandingCheckout::class)->place($f['page'], $data);
    $again = app(LandingCheckout::class)->place($f['page'], $data);
    expect($again->id)->toBe($order->id)->and(Order::count())->toBe(1);
    session()->forget('landing_cart.'.$f['page']->id);
    $data = landingQuotedPayload($f, ['idempotency_key' => (string) Str::uuid()]);
    expect(fn () => app(LandingCheckout::class)->place($f['page'], $data))->toThrow(ValidationException::class);
    expect(Order::count())->toBe(1);
});
it('requires purpose-specific delivered phone proof for COD and never authenticates a customer', function () {
    $f = landingFixture(['phone_otp' => true]);
    $data = landingQuotedPayload($f);
    $cart = app(LandingCart::class)->current($f['page']);
    expect(fn () => app(LandingCheckout::class)->place($f['page'], $data))->toThrow(ValidationException::class);
    app(CheckoutPhoneProof::class)->send($cart, $data['phone']);
    $challenge = CheckoutPhoneChallenge::firstOrFail();
    $sms = SmsDelivery::findOrFail($challenge->sms_delivery_id);
    preg_match('/[0-9]{6}/', $sms->payload, $m);
    $code = $m[0];
    expect(fn () => app(CheckoutPhoneProof::class)->verify($cart, $data['phone'], $code))->toThrow(ValidationException::class);
    app(Sms::class)->deliver($sms->id);
    app(CheckoutPhoneProof::class)->verify($cart, $data['phone'], $code);
    $this->assertGuest('web');
    $order = app(LandingCheckout::class)->place($f['page'], $data);
    expect($challenge->refresh()->used_at)->not->toBeNull()->and($order->user_id)->toBeNull();
    $this->assertGuest('web');
});
it('renders single mode and keeps the thank-you receipt session scoped', function () {
    $f = landingFixture();
    app(Settings::class)->set('primary_landing_page', $f['page']->id);
    app(Settings::class)->set('store_mode', 'single');
    $this->get('/')->assertOk()->assertSee($f['page']->title)->assertSee('/build/assets/landing-', false);
    $this->get('/shop')->assertRedirect(route('home'));
    $this->get('/cart')->assertRedirect(route('home'));
    $data = landingQuotedPayload($f);
    $this->postJson(route('landing.place', $f['page']->slug), $data)->assertOk();
    $order = Order::firstOrFail();
    $this->get(route('landing.thanks', $f['page']->slug))->assertOk()->assertSee($order->number)->assertHeader('Referrer-Policy', 'no-referrer');
    session()->forget(['placed_orders', 'landing_orders']);
    $this->get(route('landing.thanks', $f['page']->slug))->assertNotFound();
});
it('round trips reusable templates and rejects unsafe imports or imported record references', function () {
    $this->actingAs(catalogAdmin('content-editor'), 'admin');
    app(LandingTemplates::class)->seed();
    expect(LandingTemplate::count())->toBe(6);
    $f = landingFixture();
    $template = app(LandingTemplates::class)->save($f['page'], 'Original layout');
    $json = app(LandingTemplates::class)->export($template);
    $copy = app(LandingTemplates::class)->import($json);
    expect($copy->sections)->toBe($template->sections)->and($copy->is_system)->toBeFalse();
    $payload = json_decode($json, true);
    $payload['form_config']['bump_package_id'] = 999;
    expect(fn () => app(LandingTemplates::class)->import(json_encode($payload)))->toThrow(HttpException::class);
});
it('renders all three form modes and restricts builder resources by permission', function () {
    $f = landingFixture();
    foreach (['inline', 'popup', 'sticky'] as $mode) {
        $f['page']->update(['form_config' => array_replace($f['page']->form_config, ['mode' => $mode])]);
        $this->get(route('landing.show', $f['page']->slug))->assertOk()->assertSee('name="package_id"', false)->assertSee('name="idempotency_key"', false);
    }
    $this->actingAs(catalogAdmin('support'), 'admin')->get('/admin/landing-pages')->assertForbidden();
});
it('shows the real landing and package admin screens for permitted staff', function () {
    $f = landingFixture();
    $this->actingAs(catalogAdmin('content-editor'), 'admin')->get('/admin/landing-pages')->assertOk()->assertSee($f['page']->title);
    $this->get('/admin/product-packages')->assertOk();
    $this->get('/admin/landing-templates')->assertOk();
    $this->get('/admin/store-mode')->assertOk();
});

function landingOfferFixture(): array
{
    $f = landingFixture();
    $upsell = ProductPackage::factory()->create(['landing_page_id' => $f['page']->id, 'product_id' => $f['product']->id, 'role' => 'upsell', 'qty' => 1, 'price' => 20000]);
    $downsell = ProductPackage::factory()->create(['landing_page_id' => $f['page']->id, 'product_id' => $f['product']->id, 'role' => 'downsell', 'qty' => 1, 'price' => 15000]);
    $f['page']->update(['form_config' => array_replace($f['page']->form_config, ['upsell_package_id' => $upsell->id, 'downsell_package_id' => $downsell->id])]);
    $order = app(LandingCheckout::class)->place($f['page'], landingQuotedPayload($f));
    session(['placed_orders' => [$order->id], 'landing_orders.'.$f['page']->id => $order->id]);

    return $f + compact('order', 'upsell', 'downsell');
}
it('adds one offer once without changing shipping receipts or original prices', function () {
    $f = landingOfferFixture();
    $o = $f['order'];
    $original = $o->items()->firstOrFail()->getAttributes();
    app(RecordPaymentReceipt::class)->execute($o, $o->total, 'cash received', (string) Str::uuid(), null);
    $paid = $o->paid_amount;
    $service = app(LandingOffers::class);
    $offer = $service->current($o);
    $service->decide($o, 'upsell', true, $offer['hash']);
    $service->decide($o, 'upsell', true, $offer['hash']);
    $o->refresh();
    expect($o->total)->toBe(153000)->and($o->shipping)->toBe(8000)->and($o->paid_amount)->toBe($paid)->and($o->payment_status)->toBe('partial')->and($o->payments()->count())->toBe(1)->and($o->items()->count())->toBe(2)->and($o->items()->first()->getAttributes())->toBe($original)->and($f['product']->refresh()->qty)->toBe(16)->and($service->current($o))->toBeNull();
});
it('offers a downsell after decline and checks ownership expiry stale amounts and pending payments', function () {
    $f = landingOfferFixture();
    $o = $f['order'];
    $service = app(LandingOffers::class);
    $service->decide($o, 'upsell', false, null);
    $offer = $service->current($o);
    expect($offer['stage'])->toBe('downsell');
    $f['downsell']->update(['price' => 18000]);
    expect(fn () => $service->decide($o, 'downsell', true, $offer['hash']))->toThrow(ValidationException::class);
    session()->forget('placed_orders');
    expect(fn () => $service->current($o))->toThrow(HttpException::class);
    session(['placed_orders' => [$o->id]]);
    $this->travel(16)->minutes();
    expect($service->current($o))->toBeNull();
    expect(fn () => $service->decide($o, 'downsell', true, $offer['hash']))->toThrow(ValidationException::class);
    expect($o->items()->count())->toBe(1);
});
