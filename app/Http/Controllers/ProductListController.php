<?php

namespace App\Http\Controllers;

use App\Domain\Customers\Actions\ProductLists;
use Illuminate\Http\Request;

final class ProductListController extends Controller
{
    public function wishlist(): mixed
    {
        return view('themes.default.marketing.lists', ['kind' => 'wishlist', 'products' => app(ProductLists::class)->products('wishlist')]);
    }

    public function compare(): mixed
    {
        return view('themes.default.marketing.lists', ['kind' => 'compare', 'products' => app(ProductLists::class)->products('compare')]);
    }

    public function store(Request $r, string $kind): mixed
    {
        $d = $r->validate(['product_id' => 'required|integer', 'add' => 'required|boolean']);
        app(ProductLists::class)->set($kind, $d['product_id'], $r->boolean('add'));

        return back()->with('status', __('marketing.list_saved'));
    }
}
