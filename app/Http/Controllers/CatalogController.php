<?php

namespace App\Http\Controllers;

use App\Domain\Catalog\Actions\CatalogSearch;
use App\Domain\Catalog\Models\Product;
use App\Domain\Content\Actions\ResolveRedirect;
use App\Domain\Marketing\Actions\Tracking;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CatalogController extends Controller
{
    public function shop(Request $request): View
    {
        if ($request->routeIs('search') && trim((string) $request->query('q')) !== '') {
            DB::table('search_logs')->insert(['term' => mb_substr(trim((string) $request->query('q')), 0, 100), 'results' => app(CatalogSearch::class)->apply(Product::published(), (string) $request->query('q'))->count(), 'created_at' => now(), 'updated_at' => now()]);
        }

        return view('themes.default.catalog', ['categorySlug' => $request->route('slug') && $request->routeIs('category') ? $request->route('slug') : null, 'brandSlug' => $request->route('slug') && $request->routeIs('brand') ? $request->route('slug') : null]);
    }

    public function product(Request $request, string $slug): View|RedirectResponse
    {
        $product = Product::published()->where('slug', $slug)->first();
        if (! $product) {
            if ($redirect = app(ResolveRedirect::class)->execute('/product/'.$slug)) {
                return $redirect;
            }
            abort(404);
        }
        app(Tracking::class)->product($product);
        DB::table('products')->where('id', $product->id)->increment('views');
        session(['recent_products' => array_slice(array_values(array_unique(array_merge([$product->id], (array) session('recent_products', [])))), 0, 8)]);

        return view('themes.default.product', ['product' => $product]);
    }
}
