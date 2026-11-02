<?php

use App\Domain\Settings\Models\Currency;
use App\Http\Controllers\AccountSetupController;
use App\Http\Controllers\AdminCodeController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CatalogExportController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CommerceExportController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ContentController;
use App\Http\Controllers\CustomerAccountController;
use App\Http\Controllers\CustomerAuthController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OffersController;
use App\Http\Controllers\OrderDocumentController;
use App\Http\Controllers\OrderTrackingController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PhoneCodeController;
use App\Http\Controllers\ProductFeedbackController;
use App\Http\Controllers\ProductListController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', [ContentController::class, 'home'])->name('home');
Route::get('/shop', [CatalogController::class, 'shop'])->name('shop');
Route::get('/search', [CatalogController::class, 'shop'])->middleware('throttle:60,1,store-search')->name('search');
Route::get('/category/{slug}', [CatalogController::class, 'shop'])->name('category');
Route::get('/brand/{slug}', [CatalogController::class, 'shop'])->name('brand');
Route::get('/product/{slug}', [CatalogController::class, 'product'])->name('product');

Route::post('/locale', function (Request $request) {
    $data = $request->validate(['locale' => 'required|in:en,bn']);
    $request->session()->put('locale', $data['locale']);
    if (auth('web')->check()) {
        auth('web')->user()->update(['locale' => $data['locale']]);
    }

    return redirect()->route('home')->withCookie(cookie('locale', $data['locale'], 525600));
})->middleware('throttle:30,1,store-locale')->name('locale.update');

Route::get('/admin/catalog-export', CatalogExportController::class)->name('catalog.export');

Route::view('/cart', 'themes.default.cart')->name('cart');
Route::post('/cart/items', [CartController::class, 'add'])->middleware('throttle:120,1,store-cart-add')->name('cart.add');
Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout');
Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:10,1,store-checkout-store')->name('checkout.store');
Route::post('/checkout/quote', [CheckoutController::class, 'quote'])->middleware('throttle:60,1,store-checkout-quote')->name('checkout.quote');
Route::get('/checkout/success/{order}', [CheckoutController::class, 'success'])->name('checkout.success');
Route::get('/locations/children', [LocationController::class, 'children'])->middleware('throttle:120,1,store-locations-children')->name('locations.children');
Route::get('/locations/shipping', [LocationController::class, 'shipping'])->middleware('throttle:60,1,store-locations-shipping')->name('locations.shipping');
Route::get('/account/setup/{token}', [AccountSetupController::class, 'show'])->middleware('throttle:10,1,store-setup-show')->name('account.setup');
Route::post('/account/setup/{token}', [AccountSetupController::class, 'store'])->middleware('throttle:5,1,store-setup-store')->name('account.setup.store');
Route::post('/display-currency', function (Request $request) {
    $data = $request->validate(['currency' => 'required|string|size:3']);
    $currency = Currency::where('code', $data['currency'])->where('is_active', true)->firstOrFail();
    session(['display_currency' => $currency->code]);
    if (auth('web')->check()) {
        auth('web')->user()->update(['currency' => $currency->code]);
    }

    return redirect()->back()->withCookie(cookie('display_currency', $currency->code, 525600));
})->name('currency.update');

Route::view('/login', 'themes.default.account.auth', ['mode' => 'login'])->name('login');
Route::view('/register', 'themes.default.account.auth', ['mode' => 'register'])->name('register');
Route::view('/email-code', 'themes.default.account.auth', ['mode' => 'otp'])->name('otp');
Route::post('/login', [CustomerAuthController::class, 'login'])->middleware('throttle:5,1,store-login');
Route::post('/register', [CustomerAuthController::class, 'register'])->middleware('throttle:5,1,store-register');
Route::post('/email-code/send', [CustomerAuthController::class, 'sendCode'])->middleware('throttle:5,1,store-email-send')->name('otp.send');
Route::post('/email-code/verify', [CustomerAuthController::class, 'verifyCode'])->middleware('throttle:10,1,store-email-verify')->name('otp.verify');
Route::view('/forgot-password', 'themes.default.account.auth', ['mode' => 'forgot_password'])->name('password.request');
Route::post('/forgot-password', [CustomerAuthController::class, 'forgot'])->middleware('throttle:3,1,store-password-email')->name('password.email');
Route::view('/reset-password/{token}', 'themes.default.account.auth', ['mode' => 'reset_password'])->name('password.reset');
Route::post('/reset-password', [CustomerAuthController::class, 'reset'])->middleware('throttle:5,1,store-password-reset')->name('password.update');
Route::match(['get', 'post'], '/track-order', OrderTrackingController::class)->middleware('throttle:10,1,store-tracking')->name('track-order');
Route::middleware('auth:web')->group(function () {
    Route::post('/logout', [CustomerAuthController::class, 'logout'])->name('logout');
    Route::get('/account', [CustomerAccountController::class, 'index'])->name('account');
    Route::get('/account/data-export', [CustomerAccountController::class, 'export'])->middleware('throttle:3,1,store-customer-export')->name('account.export');
    Route::post('/account/privacy-request', [CustomerAccountController::class, 'privacyRequest'])->middleware('throttle:3,1,store-customer-privacy')->name('account.privacy');
    Route::get('/account/orders', [CustomerAccountController::class, 'index'])->name('account.orders');
    Route::post('/account/profile', [CustomerAccountController::class, 'profile'])->name('account.profile');
    Route::post('/account/password', [CustomerAccountController::class, 'password'])->name('account.password');
    Route::get('/account/orders/{number}', [CustomerAccountController::class, 'order'])->name('account.order');
    Route::post('/account/orders/{number}', [CustomerAccountController::class, 'action'])->name('account.order.action');
    Route::get('/account/addresses', [CustomerAccountController::class, 'addresses'])->name('account.addresses');
    Route::post('/account/addresses', [CustomerAccountController::class, 'address'])->name('account.address.store');
    Route::post('/account/addresses/{address}', [CustomerAccountController::class, 'address'])->name('account.address.update');
    Route::delete('/account/addresses/{address}', [CustomerAccountController::class, 'deleteAddress'])->name('account.address.delete');
    Route::get('/account/downloads', [CustomerAccountController::class, 'downloads'])->name('account.downloads');
    Route::get('/account/download/{entitlement}', [CustomerAccountController::class, 'download'])->middleware('signed')->name('account.download');
    Route::get('/account/documents/{order}/{type}', [OrderDocumentController::class, 'customer'])->middleware('signed')->name('account.document');
});
Route::get('/admin/order-documents/{order}/{type}', [OrderDocumentController::class, 'staff'])->middleware(['auth:admin', 'signed'])->name('admin.order.document');
Route::get('/admin/order-proofs/{order}', [OrderDocumentController::class, 'proof'])->middleware(['auth:admin', 'signed'])->name('admin.order.proof');

Route::get('/admin/commerce-export/{type}', CommerceExportController::class)->middleware(['auth:admin', 'throttle:3,1,store-commerce-export'])->name('commerce.export');

Route::get('/orders/{order:number}/pay', [PaymentController::class, 'order'])->name('payments.order');
Route::post('/orders/{order:number}/pay', [PaymentController::class, 'start'])->middleware('throttle:10,1,payment-start')->name('payments.start');
Route::get('/payments/{attempt:uuid}', [PaymentController::class, 'status'])->name('payments.status');
Route::post('/payments/{attempt:uuid}/check', [PaymentController::class, 'check'])->middleware('throttle:10,1,payment-check')->name('payments.check');
Route::match(['get', 'post'], '/payments/fake/{attempt:uuid}', [PaymentController::class, 'fake'])->middleware(['signed:relative', 'throttle:30,1,payment-fake'])->name('payments.fake');

Route::get('/phone-code', [PhoneCodeController::class, 'show'])->name('phone-code');
Route::post('/phone-code/send', [PhoneCodeController::class, 'send'])->middleware('throttle:5,1,phone-code-send')->name('phone-code.send');
Route::post('/phone-code/verify', [PhoneCodeController::class, 'verify'])->middleware('throttle:10,1,phone-code-verify')->name('phone-code.verify');
Route::get('/admin/email-code', [AdminCodeController::class, 'show'])->name('admin.otp');
Route::post('/admin/email-code', [AdminCodeController::class, 'verify'])->middleware('throttle:10,1,admin-code-verify')->name('admin.otp.verify');

Route::get('/page/{slug}', [ContentController::class, 'page'])->name('page');
Route::get('/blog', [ContentController::class, 'blog'])->name('blog');
Route::get('/blog/{slug}', [ContentController::class, 'post'])->name('blog.post');
Route::get('/faq', [ContentController::class, 'faq'])->name('faq');
Route::get('/admin/content-preview/{kind}/{id}', [ContentController::class, 'preview'])->middleware(['auth:admin', 'signed'])->name('content.preview');

Route::get('/contact', [ContactController::class, 'show'])->name('contact');
Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:5,60,store-contact')->name('contact.store');

Route::get('/wishlist', [ProductListController::class, 'wishlist'])->name('wishlist');
Route::get('/compare', [ProductListController::class, 'compare'])->name('compare');
Route::post('/product-lists/{kind}', [ProductListController::class, 'store'])->middleware('throttle:60,1,product-lists')->name('product-list.store');

Route::middleware('auth:web')->group(function () {
    Route::get('/account/notifications', [NotificationController::class, 'index'])->name('notifications');
    Route::post('/account/notifications/read-all', [NotificationController::class, 'readAll'])->middleware('throttle:30,1,notice-read-all')->name('notifications.read-all');
    Route::post('/account/notifications/{notification}/read', [NotificationController::class, 'read'])->middleware('throttle:60,1,notice-read')->name('notifications.read');
    Route::post('/account/notification-preferences', [NotificationController::class, 'preferences'])->middleware('throttle:20,1,notice-preferences')->name('notifications.preferences');
    Route::post('/products/{product}/watch', [NotificationController::class, 'watch'])->middleware('throttle:30,1,product-watch')->name('product.watch');
});

Route::post('/account/product-alerts/{watch}/stop', [NotificationController::class, 'stop'])->middleware(['auth:web', 'throttle:30,1,product-watch-stop'])->name('product.watch.stop');

Route::middleware('auth:web')->group(function () {
    Route::post('/products/{product}/reviews', [ProductFeedbackController::class, 'review'])->middleware('throttle:5,60,product-review-submit')->name('product.review');
    Route::post('/products/{product}/questions', [ProductFeedbackController::class, 'question'])->middleware('throttle:5,60,product-question-submit')->name('product.question');
    Route::post('/reviews/{review}/helpful', [ProductFeedbackController::class, 'vote'])->middleware('throttle:60,1,review-helpful')->name('review.vote');
    Route::get('/account/reviews', [ProductFeedbackController::class, 'account'])->name('account.reviews');
});

Route::post('/newsletter', [NewsletterController::class, 'subscribe'])->middleware('throttle:5,60,newsletter-signup')->name('newsletter.subscribe');
Route::get('/newsletter/confirm/{token}', [NewsletterController::class, 'confirm'])->middleware('throttle:20,1,newsletter-confirm')->name('newsletter.confirm');
Route::match(['get', 'post'], '/newsletter/unsubscribe/{token}', [NewsletterController::class, 'unsubscribe'])->middleware('throttle:30,1,newsletter-unsubscribe')->name('newsletter.unsubscribe');

Route::get('/offers', [OffersController::class, 'index'])->name('offers');
Route::post('/account/saved-coupons', [OffersController::class, 'save'])->middleware(['auth:web', 'throttle:10,1,coupon-reminder-save'])->name('coupon.save');
Route::post('/account/saved-coupons/{saved}/remove', [OffersController::class, 'remove'])->middleware(['auth:web', 'throttle:30,1,coupon-reminder-remove'])->name('coupon.remove');
