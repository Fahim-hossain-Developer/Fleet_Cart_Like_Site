<?php

namespace App\Http\Controllers;

use App\Domain\Catalog\Models\Product;
use App\Domain\Marketing\Actions\FlashPricing;
use App\Domain\Marketing\Actions\SaveCouponReminder;
use App\Domain\Marketing\Models\Coupon;
use App\Domain\Marketing\Models\FlashSaleItem;
use App\Domain\Marketing\Models\SavedCoupon;
use Illuminate\Http\Request;

final class OffersController extends Controller
{
    public function index(): mixed
    {
        $items = FlashSaleItem::where('is_active', true)->whereColumn('sold', '<', 'quota')->whereHas('sale', fn ($q) => $q->where('is_active', true)->where('starts_at', '<=', now())->where('ends_at', '>', now()))->pluck('product_id');
        $products = Product::published()->whereIn('id', $items)->with(['media', 'variants'])->limit(48)->get()->filter(fn ($p) => app(FlashPricing::class)->active($p));

        return view('themes.default.marketing.offers', ['products' => $products, 'coupons' => Coupon::where('is_public', true)->where('is_active', true)->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))->limit(20)->get()]);
    }

    public function save(Request $r): mixed
    {
        $d = $r->validate(['code' => 'required|string|max:50']);
        app(SaveCouponReminder::class)->execute(auth('web')->user(), $d['code']);

        return back()->with('status', __('orders.saved'));
    }

    public function remove(int $saved): mixed
    {
        SavedCoupon::where('user_id', auth('web')->id())->findOrFail($saved)->delete();

        return back()->with('status', __('orders.saved'));
    }
}
