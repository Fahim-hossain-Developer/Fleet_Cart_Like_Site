<?php

namespace App\Http\Middleware;

use App\Domain\Catalog\Models\Product;
use App\Domain\Content\Models\LandingPage;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SingleStore
{
    public function handle(Request $r, Closure $next): Response
    {
        if ($r->isMethod('get') && store_setting('store_mode', 'multi') === 'single') {
            if ($r->routeIs('product')) {
                $slug = $r->route('slug');
                $id = Product::published()->where('slug', $slug)->value('id');
                $page = LandingPage::published()->where('product_id', $id)->whereHas('packages', fn ($q) => $q->where('role', 'primary')->where('is_active', true))->first();

                return $page ? redirect()->route('landing.show', $page->slug) : redirect()->route('home');
            }
            if ($r->routeIs('cart', 'checkout')) {
                return redirect()->route('home');
            }
            if (! store_setting('single_show_catalog', false) && $r->routeIs('shop', 'search', 'category', 'brand', 'product')) {
                return redirect()->route('home');
            }
        }

        return $next($r);
    }
}
