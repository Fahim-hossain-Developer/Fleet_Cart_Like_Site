<?php

use App\Domain\Settings\Models\Currency;
use App\Http\Controllers\AccountSetupController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CatalogExportController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\LocationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::view('/', 'themes.default.home')->name('home');
Route::get('/shop', [CatalogController::class, 'shop'])->name('shop');
Route::get('/search', [CatalogController::class, 'shop'])->middleware('throttle:60,1')->name('search');
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
})->middleware('throttle:30,1')->name('locale.update');

Route::get('/admin/catalog-export', CatalogExportController::class)->name('catalog.export');

Route::view('/cart', 'themes.default.cart')->name('cart');
Route::post('/cart/items', [CartController::class, 'add'])->middleware('throttle:120,1')->name('cart.add');
Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout');
Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:10,1')->name('checkout.store');
Route::post('/checkout/quote', [CheckoutController::class, 'quote'])->middleware('throttle:60,1')->name('checkout.quote');
Route::get('/checkout/success/{order}', [CheckoutController::class, 'success'])->name('checkout.success');
Route::get('/locations/children', [LocationController::class, 'children'])->middleware('throttle:120,1')->name('locations.children');
Route::get('/locations/shipping', [LocationController::class, 'shipping'])->middleware('throttle:60,1')->name('locations.shipping');
Route::get('/account/setup/{token}', [AccountSetupController::class, 'show'])->middleware('throttle:10,1')->name('account.setup');
Route::post('/account/setup/{token}', [AccountSetupController::class, 'store'])->middleware('throttle:5,1')->name('account.setup.store');
Route::post('/display-currency', function (Request $request) {
    $data = $request->validate(['currency' => 'required|string|size:3']);
    $currency = Currency::where('code', $data['currency'])->where('is_active', true)->firstOrFail();
    session(['display_currency' => $currency->code]);
    if (auth('web')->check()) {
        auth('web')->user()->update(['currency' => $currency->code]);
    }

    return redirect()->back()->withCookie(cookie('display_currency', $currency->code, 525600));
})->name('currency.update');
