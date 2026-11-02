<?php

use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CatalogExportController;
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

    return redirect()->route('home')->withCookie(cookie('locale', $data['locale'], 525600));
})->middleware('throttle:30,1')->name('locale.update');

Route::get('/admin/catalog-export', CatalogExportController::class)->name('catalog.export');
