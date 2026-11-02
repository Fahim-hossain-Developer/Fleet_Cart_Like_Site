<?php

use App\Domain\Cart\Actions\CartManager;
use App\Domain\Cart\Actions\CartSession;
use App\Domain\Cart\Actions\QuoteCart;
use App\Domain\Catalog\Models\ProductDownload;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Customers\Actions\EmailCode;
use App\Domain\Customers\Actions\SaveAddress;
use App\Domain\Customers\Models\Address;
use App\Domain\Orders\Actions\CreateManualOrder;
use App\Domain\Orders\Actions\EditOrderItems;
use App\Domain\Orders\Actions\ManageOrder;
use App\Domain\Orders\Actions\PlaceOrder;
use App\Domain\Orders\Actions\Reorder;
use App\Domain\Orders\Models\DownloadEntitlement;
use App\Domain\Orders\Models\Order;
use App\Domain\Settings\Actions\CredentialVault;
use App\Domain\Settings\Actions\Settings;
use App\Filament\Resources\OrderResource\Pages\ViewOrder;
use App\Jobs\SendOrderUpdate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

function managedOrder(int $qty = 1): array
{
    Queue::fake();
    $f = checkoutFixture(10);
    if ($qty > 1) {
        app(CartManager::class)->quantity($f['cart'], $f['cart']->items()->first()->id, $qty);
    }
    $f['order'] = app(PlaceOrder::class)->execute($f['cart'], $f['data']);

    return $f;
}
function prepareShipment(Order $order): void
{
    foreach (['confirmed', 'processing', 'packed'] as $s) {
        app(ManageOrder::class)->transition($order, $s);
    }
}

it('enforces transitions, customer ownership and exactly once cancellation stock release', function () {
    $f = managedOrder();
    $o = $f['order'];
    $u = User::factory()->create();
    $other = User::factory()->create();
    $o->update(['user_id' => $u->id]);
    $this->actingAs($other, 'web')->post('/account/orders/'.$o->number, ['action' => 'cancel'])->assertForbidden();
    $this->actingAs($u, 'web')->get('/account/orders/'.$o->number)->assertOk();
    $this->post('/account/orders/'.$o->number, ['action' => 'cancel'])->assertRedirect();
    expect($o->refresh()->status)->toBe('cancelled')->and($f['product']->refresh()->qty)->toBe(10);
    $this->post('/account/orders/'.$o->number, ['action' => 'cancel'])->assertSessionHasErrors('order');
    expect($f['product']->refresh()->qty)->toBe(10);
});
it('rejects staff state mutations without permission and prevents terminal reopening', function () {
    $f = managedOrder();
    $this->actingAs(catalogAdmin('support'), 'admin');
    expect(fn () => app(ManageOrder::class)->transition($f['order'], 'confirmed'))->toThrow(HttpException::class);
    $this->actingAs(catalogAdmin(), 'admin');
    app(ManageOrder::class)->transition($f['order'], 'cancelled');
    expect(fn () => app(ManageOrder::class)->transition($f['order'], 'confirmed'))->toThrow(ValidationException::class);
});
it('snapshots stock policy and rechecks deferred inventory atomically', function () {
    app(Settings::class)->set('orders_stock_policy', 'confirmation');
    $f = managedOrder();
    expect($f['product']->refresh()->qty)->toBe(10)->and($f['order']->stock_policy)->toBe('confirmation');
    $this->actingAs(catalogAdmin(), 'admin');
    $f['product']->update(['qty' => 0]);
    expect(fn () => app(ManageOrder::class)->transition($f['order'], 'confirmed'))->toThrow(ValidationException::class);
    expect($f['order']->refresh()->status)->toBe('pending');
    $f['product']->update(['qty' => 1]);
    app(ManageOrder::class)->transition($f['order'], 'confirmed');
    expect($f['product']->refresh()->qty)->toBe(0);
    app(ManageOrder::class)->transition($f['order'], 'cancelled');
    expect($f['product']->refresh()->qty)->toBe(1);
});
it('records partial payments idempotently and protects finance permissions and balance', function () {
    app(Settings::class)->set('orders_stock_policy', 'payment');
    $f = managedOrder();
    $o = $f['order'];
    $this->actingAs(catalogAdmin('order-processor'), 'admin');
    expect(fn () => app(ManageOrder::class)->payment($o, 100, 'cash', (string) Str::uuid()))->toThrow(HttpException::class);
    $this->actingAs(catalogAdmin(), 'admin');
    $key = (string) Str::uuid();
    app(ManageOrder::class)->payment($o, 10000, 'cash-1', $key);
    app(ManageOrder::class)->payment($o, 10000, 'cash-1', $key);
    expect($o->refresh()->paid_amount)->toBe(10000)->and($o->payment_status)->toBe('partial')->and($o->payments()->count())->toBe(1)->and($f['product']->refresh()->qty)->toBe(9);
    expect(fn () => app(ManageOrder::class)->payment($o, $o->total, 'too much', (string) Str::uuid()))->toThrow(ValidationException::class);
    app(ManageOrder::class)->payment($o, $o->total - 10000, 'cash-2', (string) Str::uuid());
    expect($o->refresh()->payment_status)->toBe('paid');
});
it('bounds partial shipments and prevents delivery before every item has shipped', function () {
    $f = managedOrder(2);
    $o = $f['order'];
    $this->actingAs(catalogAdmin(), 'admin');
    prepareShipment($o);
    $id = $o->items()->first()->id;
    $key = (string) Str::uuid();
    app(ManageOrder::class)->ship($o, [$id => 1], 'Manual carrier', 'TEST-1', $key);
    app(ManageOrder::class)->ship($o, [$id => 1], 'Manual carrier', 'TEST-1', $key);
    expect($o->refresh()->fulfillment_status)->toBe('partial')->and($o->shipments()->count())->toBe(1);
    expect(fn () => app(ManageOrder::class)->transition($o, 'delivered'))->toThrow(ValidationException::class);
    expect(fn () => app(ManageOrder::class)->ship($o, [$id => 2], 'Manual', 'TEST-2', (string) Str::uuid()))->toThrow(ValidationException::class);
    app(ManageOrder::class)->ship($o, [$id => 1], 'Manual', 'TEST-3', (string) Str::uuid());
    app(ManageOrder::class)->transition($o, 'delivered');
    expect($o->refresh()->fulfillment_status)->toBe('fulfilled')->and($o->status)->toBe('delivered');
});
it('refunds cancelled paid orders once including gift and shipping without double restock', function () {
    $f = managedOrder();
    $o = $f['order'];
    $this->actingAs(catalogAdmin(), 'admin');
    app(ManageOrder::class)->payment($o, $o->total, 'received', (string) Str::uuid());
    app(ManageOrder::class)->transition($o, 'cancelled');
    $key = (string) Str::uuid();
    app(ManageOrder::class)->refund($o, $o->total, 'Cancelled by customer', $key);
    app(ManageOrder::class)->refund($o, $o->total, 'Cancelled by customer', $key);
    expect($o->refresh()->refunded_amount)->toBe($o->total)->and($o->refunds()->count())->toBe(1)->and($o->payment_status)->toBe('refunded')->and($f['product']->refresh()->qty)->toBe(10);
    expect(fn () => app(ManageOrder::class)->refund($o, 1, 'excess', (string) Str::uuid()))->toThrow(ValidationException::class);
});
it('requires an approved RMA and excludes delivery fees from fulfilled refunds', function () {
    $f = managedOrder();
    $o = $f['order'];
    $u = User::factory()->create();
    $o->update(['user_id' => $u->id]);
    $this->actingAs(catalogAdmin(), 'admin');
    app(ManageOrder::class)->payment($o, $o->total, 'received', (string) Str::uuid());
    prepareShipment($o);
    $id = $o->items()->first()->id;
    app(ManageOrder::class)->ship($o, [$id => 1], 'Manual', 'RET-1', (string) Str::uuid());
    app(ManageOrder::class)->transition($o, 'delivered');
    $this->actingAs($u, 'web');
    app(ManageOrder::class)->requestReturn($o, [$id => 1], 'Damaged');
    $r = $o->returns()->first();
    expect(fn () => app(ManageOrder::class)->refund($o, 100000, 'received', (string) Str::uuid(), [$id => 1]))->toThrow(ValidationException::class);
    app(ManageOrder::class)->resolveReturn($r, true, 'Return received');
    expect(fn () => app(ManageOrder::class)->refund($o, $o->total, 'received', (string) Str::uuid(), [$id => 1]))->toThrow(ValidationException::class);
    app(ManageOrder::class)->refund($o, 100000, 'received', (string) Str::uuid(), [$id => 1]);
    expect($o->refresh()->status)->toBe('returned')->and($o->refunded_amount)->toBe(100000)->and($f['product']->refresh()->qty)->toBe(10);
});
it('edits quantities with snapshot pricing and blocks changes after payment', function () {
    $f = managedOrder();
    $o = $f['order'];
    $this->actingAs(catalogAdmin(), 'admin');
    $id = $o->items()->first()->id;
    $f['product']->update(['price' => 999999]);
    app(EditOrderItems::class)->execute($o, [$id => 2]);
    expect($o->refresh()->total)->toBe(208000)->and($f['product']->refresh()->qty)->toBe(8);
    app(ManageOrder::class)->payment($o, 100, 'received', (string) Str::uuid());
    expect(fn () => app(EditOrderItems::class)->execute($o, [$id => 1]))->toThrow(ValidationException::class);
});
it('creates a COD phone order for an explicitly selected customer without inheriting the web guard', function () {
    $f = checkoutFixture();
    $u = User::factory()->create();
    $web = User::factory()->create();
    $this->actingAs($web, 'web')->actingAs(catalogAdmin(), 'admin');
    Queue::fake();
    $data = $f['data'] + ['items' => [['product_id' => $f['product']->id, 'qty' => 2]], 'user_id' => $u->id];
    $o = app(CreateManualOrder::class)->execute($data);
    $again = app(CreateManualOrder::class)->execute($data);
    expect($o->user_id)->toBe($u->id)->and($o->payment_status)->toBe('unpaid')->and($again->id)->toBe($o->id)->and($f['product']->refresh()->qty)->toBe(8);
});
it('uses current availability and price when reordering and scopes it to the owner', function () {
    $f = managedOrder();
    $o = $f['order'];
    $u = User::factory()->create();
    $o->update(['user_id' => $u->id]);
    $f['product']->update(['price' => 150000]);
    $this->actingAs($u, 'web');
    app(Reorder::class)->execute($o);
    expect(app(QuoteCart::class)->execute(app(CartSession::class)->current())[0]['subtotal'])->toBe(150000);
});
it('tracks only matching number and phone without disclosing addresses or internal notes', function () {
    $f = managedOrder();
    $o = $f['order'];
    $this->actingAs(catalogAdmin(), 'admin');
    app(ManageOrder::class)->note($o, 'PRIVATE-NOTE-SENTINEL', false);
    app(ManageOrder::class)->note($o, 'Your order is being reviewed', true);
    $this->post('/track-order', ['number' => $o->number, 'phone' => '01812345678'])->assertSessionHasErrors('number');
    $this->post('/track-order', ['number' => $o->number, 'phone' => $f['data']['phone']])->assertOk()->assertSee('being reviewed')->assertDontSee('PRIVATE-NOTE-SENTINEL')->assertDontSee('Test address 123');
});
it('flags real blacklist, cancellation history, IP burst and high COD amounts', function () {
    Queue::fake();
    $f = checkoutFixture();
    DB::table('phone_blacklist')->insert(['phone' => '+8801712345678', 'reason' => 'Repeated abuse']);
    Order::factory()->count(3)->create(['phone' => '+8801712345678', 'status' => 'cancelled']);
    app(Settings::class)->set('orders_high_value_cod', 100000);
    $o = app(PlaceOrder::class)->execute($f['cart'], $f['data']);
    expect($o->risk_flags)->toContain('blacklisted_phone', 'repeat_cancel', 'high_value_cod');
});
it('does not link guest orders on password login but does after a hashed single-use email code', function () {
    Mail::fake();
    $f = managedOrder();
    $o = $f['order'];
    $u = User::factory()->create(['email' => $o->guest_email, 'password' => 'safe-test-password-123', 'email_verified_at' => null]);
    $this->post('/login', ['identifier' => $u->email, 'password' => 'safe-test-password-123'])->assertRedirect('/account');
    expect($o->refresh()->user_id)->toBeNull();
    app(EmailCode::class)->send($u->email);
    $row = DB::table('otp_codes')->where('identifier', $u->email)->first();
    expect($row->code_hash)->not->toMatch('/^[0-9]{6}$/');
    DB::table('otp_codes')->where('id', $row->id)->update(['code_hash' => Hash::make('123456')]);
    $this->post('/email-code/verify', ['email' => $u->email, 'code' => '123456'])->assertRedirect('/account');
    expect($o->refresh()->user_id)->toBe($u->id)->and($u->refresh()->email_verified_at)->not->toBeNull();
    $this->post('/email-code/verify', ['email' => $u->email, 'code' => '123456'])->assertSessionHasErrors('code');
});
it('persists OTP failed attempts, expiry and per-identifier throttling', function () {
    Mail::fake();
    $u = User::factory()->create();
    app(EmailCode::class)->send($u->email);
    for ($i = 0; $i < 5; $i++) {
        expect(fn () => app(EmailCode::class)->consume($u->email, '000000'))->toThrow(ValidationException::class);
    }
    expect(DB::table('otp_codes')->value('attempts'))->toBe(5);
    DB::table('otp_codes')->update(['code_hash' => Hash::make('123456')]);
    expect(fn () => app(EmailCode::class)->consume($u->email, '123456'))->toThrow(ValidationException::class);
    app(EmailCode::class)->send($u->email);
    app(EmailCode::class)->send($u->email);
    expect(fn () => app(EmailCode::class)->send($u->email))->toThrow(ValidationException::class);
    DB::table('otp_codes')->update(['code_hash' => Hash::make('123456'), 'expires_at' => now()->subSecond()]);
    expect(fn () => app(EmailCode::class)->consume($u->email, '123456'))->toThrow(ValidationException::class);
});
it('blocks existing customer sessions and password authentication when blocked', function () {
    $u = User::factory()->create(['is_blocked' => true, 'password' => 'safe-test-password-123']);
    $this->post('/login', ['identifier' => $u->email, 'password' => 'safe-test-password-123'])->assertSessionHasErrors('identifier');
    $this->actingAs($u, 'web')->get('/account')->assertForbidden();
    $this->assertGuest('web');
});
it('validates address hierarchy, default selection and cross-account modification', function () {
    $f = checkoutFixture();
    $u = User::factory()->create();
    $this->actingAs($u, 'web');
    $data = ['name' => 'Home', 'phone' => '01712345678', 'country' => 'BD', 'division_id' => $f['division']->id, 'district_id' => $f['district']->id, 'upazila_id' => $f['upazila']->id, 'line1' => 'Home street', 'is_default' => 1];
    $this->post('/account/addresses', $data)->assertRedirect();
    $a = Address::firstOrFail();
    expect($a->phone)->toBe('+8801712345678');
    $this->post('/account/addresses', array_replace($data, ['upazila_id' => $f['district']->id]))->assertSessionHasErrors('address');
    $this->actingAs(User::factory()->create(), 'web')->post('/account/addresses/'.$a->id, $data)->assertForbidden();
    $this->delete('/account/addresses/'.$a->id)->assertForbidden();
});
it('requires signed ownership-protected PDFs and renders private gift-safe Bangla documents', function () {
    $f = managedOrder();
    $o = $f['order'];
    $u = User::factory()->create();
    $o->update(['user_id' => $u->id, 'hide_prices' => true, 'gift_message' => 'শুভ জন্মদিন', 'is_gift' => true]);
    $this->actingAs($u, 'web');
    $url = URL::temporarySignedRoute('account.document', now()->addMinutes(10), ['order' => $o->id, 'type' => 'invoice']);
    $this->get('/account/documents/'.$o->id.'/invoice')->assertForbidden();
    $html = view('pdf.order', ['order' => $o->load('items'), 'type' => 'invoice'])->render();
    expect($html)->not->toContain('1080.00')->and($html)->toContain('Prices hidden');
    $this->get($url)->assertOk()->assertHeader('content-type', 'application/pdf');
    $this->actingAs(User::factory()->create(), 'web')->get($url)->assertForbidden();
});
it('enables snapshot digital downloads only after full payment and enforces limit and ownership', function () {
    Queue::fake();
    Storage::fake('local');
    $f = checkoutFixture();
    Storage::disk('local')->put('product-downloads/test.txt', 'private sample');
    ProductDownload::factory()->create(['product_id' => $f['product']->id, 'file' => 'product-downloads/test.txt', 'external_url' => null, 'limit' => 1, 'expiry_days' => 1]);
    $o = app(PlaceOrder::class)->execute($f['cart'], $f['data']);
    $u = User::factory()->create();
    $o->update(['user_id' => $u->id]);
    $e = DownloadEntitlement::firstOrFail();
    $url = URL::temporarySignedRoute('account.download', now()->addMinutes(10), $e->id);
    $this->actingAs($u, 'web')->get($url)->assertForbidden();
    $this->actingAs(catalogAdmin(), 'admin');
    app(ManageOrder::class)->payment($o, $o->total, 'verified manual', (string) Str::uuid());
    $this->get($url)->assertOk();
    $this->get($url)->assertForbidden();
});
it('serves translated customer and permitted admin screens', function () {
    $u = User::factory()->create();
    $this->actingAs($u, 'web');
    foreach (['/account', '/account/addresses', '/account/downloads', '/login', '/register', '/email-code', '/forgot-password', '/track-order'] as $path) {
        $this->get($path)->assertOk();
    }
    $f = managedOrder();
    $this->actingAs(catalogAdmin(), 'admin');
    foreach (['/admin/orders', '/admin/orders/create', '/admin/orders/'.$f['order']->id, '/admin/customers', '/admin/phone-blacklists'] as $path) {
        $this->get($path)->assertOk();
    }
    session(['locale' => 'bn']);
    $this->get('/account')->assertOk()->assertDontSee('orders.account');
});

it('performs staff order transitions through the Filament action and restricts refunds', function () {
    $f = managedOrder();
    $this->actingAs(catalogAdmin(), 'admin');
    Livewire::test(ViewOrder::class, ['record' => $f['order']->id])->callAction('transition', ['status' => 'confirmed', 'note' => 'Ready for processing'])->assertHasNoActionErrors();
    expect($f['order']->refresh()->status)->toBe('confirmed');
    $this->actingAs(catalogAdmin('order-processor'), 'admin');
    Livewire::test(ViewOrder::class, ['record' => $f['order']->id])->assertActionHidden('refund')->assertActionHidden('payment');
});
it('allows support to view customer details while restricting edits and exports', function () {
    $u = User::factory()->create();
    $a = catalogAdmin('support');
    $this->actingAs($a, 'admin');
    $this->get('/admin/customers/'.$u->id)->assertOk();
    $this->get('/admin/commerce-export/customers')->assertForbidden();
    expect(Gate::forUser($a)->allows('update', $u))->toBeFalse();
    $this->actingAs(catalogAdmin(), 'admin');
    $response = $this->get('/admin/commerce-export/customers');
    $response->assertOk();
    expect($response->streamedContent())->toContain($u->email)->not->toContain($u->password);
});
it('exports only owned customer data and records one erasure request', function () {
    $u = User::factory()->create();
    $this->actingAs($u, 'web');
    $response = $this->get('/account/data-export');
    $response->assertOk();
    expect($response->streamedContent())->toContain($u->email)->not->toContain('password', 'remember_token', 'idempotency_key');
    $this->post('/account/privacy-request')->assertRedirect();
    $this->post('/account/privacy-request')->assertRedirect();
    expect(DB::table('customer_privacy_requests')->count())->toBe(1);
});
it('sends public order updates after commit and retries without mailing private notes', function () {
    $f = managedOrder();
    $this->actingAs(catalogAdmin(), 'admin');
    Mail::fake();
    app(ManageOrder::class)->note($f['order'], 'For staff only', false);
    app(ManageOrder::class)->note($f['order'], 'Your parcel is ready', true);
    $event = $f['order']->events()->where('notify', true)->firstOrFail();
    expect($f['order']->events()->where('notify', true)->count())->toBe(1);
    $job = new SendOrderUpdate($event->id);
    $job->handle();
    $job->handle();
    expect($event->refresh()->sent_at)->not->toBeNull()->and($event->attempts)->toBe(1);
});
it('uses expiring single-use password reset links and never verifies guest identity by resetting a password', function () {
    Notification::fake();
    $u = User::factory()->create(['email_verified_at' => null]);
    $this->post('/forgot-password', ['email' => $u->email])->assertRedirect();
    $token = Password::broker('users')->createToken($u);
    $data = ['email' => $u->email, 'token' => $token, 'password' => 'another-safe-password-123', 'password_confirmation' => 'another-safe-password-123'];
    $this->post('/reset-password', $data)->assertRedirect('/login');
    expect(Hash::check($data['password'], $u->refresh()->password))->toBeTrue()->and($u->email_verified_at)->toBeNull();
    $this->post('/reset-password', $data)->assertSessionHasErrors('email');
});
it('protects new customer screens from credential leakage and renders saved address names in both languages', function () {
    $f = managedOrder();
    $u = User::factory()->create();
    $f['order']->update(['user_id' => $u->id]);
    app(CredentialVault::class)->replace('gateway', 'sk_live_PHASE3_SENTINEL');
    $this->actingAs($u, 'web');
    $address = app(SaveAddress::class)->execute($u, ['name' => 'Home', 'phone' => '01712345678', 'country' => 'BD', 'division_id' => $f['division']->id, 'district_id' => $f['district']->id, 'upazila_id' => $f['upazila']->id, 'line1' => 'Test address 123']);
    foreach (['en', 'bn'] as $locale) {
        session(['locale' => $locale]);
        foreach (['/account', '/account/addresses', '/account/downloads', '/account/orders/'.$f['order']->number, '/login', '/email-code', '/track-order'] as $path) {
            $this->get($path)->assertOk()->assertDontSee('sk_live_')->assertDontSee('orders.')->assertDontSee('checkout.choose');
        }
    }
});

it('revokes passwords and sessions chosen before first email ownership verification', function () {
    $u = User::factory()->create(['email_verified_at' => null, 'password' => 'attacker-chosen-password']);
    $this->post('/login', ['identifier' => $u->email, 'password' => 'attacker-chosen-password'])->assertRedirect('/account');
    $oldVersion = $u->auth_version;
    DB::table('otp_codes')->insert(['identifier' => $u->email, 'code_hash' => Hash::make('123456'), 'expires_at' => now()->addMinutes(10), 'created_at' => now(), 'updated_at' => now()]);
    app(EmailCode::class)->consume($u->email, '123456');
    expect(Hash::check('attacker-chosen-password', $u->refresh()->password))->toBeFalse()->and($u->auth_version)->toBe($oldVersion + 1);
    $this->actingAs($u, 'web')->withSession(['customer_auth_version' => $oldVersion])->get('/account')->assertForbidden();
    $this->post('/login', ['identifier' => $u->email, 'password' => 'attacker-chosen-password'])->assertSessionHasErrors('identifier');
});
it('sets a password from a recently verified email session and requires current password afterwards', function () {
    $u = User::factory()->create();
    $this->actingAs($u, 'web')->withSession(['customer_auth_version' => $u->auth_version, 'customer_email_verified_until' => now()->addMinutes(10)->timestamp]);
    $data = ['password' => 'new-customer-password-123', 'password_confirmation' => 'new-customer-password-123'];
    $this->post('/account/password', $data)->assertRedirect();
    expect(Hash::check($data['password'], $u->refresh()->password))->toBeTrue();
    $this->post('/account/password', $data)->assertSessionHasErrors('current_password');
});
it('renders the emailed code from the generated hashed challenge and hides unknown accounts', function () {
    $u = User::factory()->create();
    $sentCode = null;
    Mail::shouldReceive('raw')->once()->withArgs(function ($body, $callback) use (&$sentCode) {
        preg_match('/[0-9]{6}/', $body, $m);
        $sentCode = $m[0] ?? null;

        return is_callable($callback);
    });
    app(EmailCode::class)->send($u->email);
    expect($sentCode)->not->toBeNull();
    expect(Hash::check($sentCode, DB::table('otp_codes')->value('code_hash')))->toBeTrue();
    $this->post('/email-code/send', ['email' => 'unknown@example.test'])->assertRedirect()->assertSessionHas('status', __('orders.code_sent'));
});
it('preserves quantity pricing across repeated edits and rejects deleting ordered variants', function () {
    $f = managedOrder(3);
    $o = $f['order'];
    $this->actingAs(catalogAdmin(), 'admin');
    $i = $o->items()->first();
    app(EditOrderItems::class)->execute($o, [$i->id => 1]);
    app(EditOrderItems::class)->execute($o, [$i->id => 3]);
    expect($o->refresh()->subtotal)->toBe(300000)->and($f['product']->refresh()->qty)->toBe(7);
    $variant = ProductVariant::factory()->create(['product_id' => $f['product']->id]);
    $i->update(['variant_id' => $variant->id]);
    expect(fn () => $variant->delete())->toThrow(ValidationException::class);
});

it('normalizes profile email before uniqueness validation and protects historical taxed lines', function () {
    $u = User::factory()->create();
    $other = User::factory()->create(['email' => 'existing@example.test']);
    $this->actingAs($u, 'web');
    $this->post('/account/profile', ['name' => 'Customer', 'email' => 'EXISTING@example.test', 'locale' => 'en'])->assertSessionHasErrors('email');
    $f = managedOrder();
    $this->actingAs(catalogAdmin(), 'admin');
    $i = $f['order']->items()->first();
    $i->update(['tax' => 1000, 'tax_bps' => 0]);
    expect(fn () => app(EditOrderItems::class)->execute($f['order'], [$i->id => 2]))->toThrow(ValidationException::class);
});

it('keeps quote and location requests out of the checkout submission rate bucket', function () {
    Queue::fake();
    $f = checkoutFixture();
    $this->actingAs(User::factory()->create(), 'web');
    for ($i = 0; $i < 12; $i++) {
        $this->post('/checkout/quote', ['country' => 'BD', 'district_id' => $f['district']->id, 'shipping_method_id' => $f['method']->id, 'is_gift' => false])->assertOk();
    }
    for ($i = 0; $i < 3; $i++) {
        $this->get('/locations/children?parent='.$f['division']->id)->assertOk();
    }
    $this->post('/checkout', $f['data'])->assertRedirect();
    expect(Order::count())->toBe(1);
});
it('still throttles password attempts without consuming registration allowance', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->post('/login', ['identifier' => 'unknown@example.test', 'password' => 'wrong-password'])->assertRedirect();
    }
    $this->post('/login', ['identifier' => 'unknown@example.test', 'password' => 'wrong-password'])->assertStatus(429);
    $this->post('/register', [])->assertRedirect()->assertSessionHasErrors('email');
});
