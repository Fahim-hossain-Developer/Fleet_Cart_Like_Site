<?php

namespace App\Http\Controllers;

use App\Domain\Cart\Actions\CartSession;
use App\Domain\Cart\Actions\QuoteCart;
use App\Domain\Marketing\Actions\ValidateCoupon;
use App\Domain\Marketing\Models\Coupon;
use App\Domain\Shipping\Actions\ShippingResolver;
use App\Domain\Shipping\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function children(Request $request): JsonResponse
    {
        $data = $request->validate(['parent' => 'required|integer']);

        return response()->json(Location::where('parent_id', $data['parent'])->get()->map(fn ($l) => ['id' => $l->id, 'name' => $l->name[app()->getLocale()] ?? $l->name['en']])->values());
    }

    public function shipping(Request $request, CartSession $session): JsonResponse
    {
        $data = $request->validate(['district' => 'required|integer']);
        $cart = $session->current();
        $lines = app(QuoteCart::class)->execute($cart);
        $subtotal = array_sum(array_column($lines, 'subtotal'));
        $promotion = app(ValidateCoupon::class)->execute($cart->coupon_id ? Coupon::find($cart->coupon_id) : null, $subtotal);
        if (collect($lines)->every(fn ($line) => $line['digital'])) {
            return response()->json([['id' => 0, 'name' => __('checkout.digital_delivery'), 'amount' => 0]]);
        }

        return response()->json(array_values(app(ShippingResolver::class)->methods('BD', (int) $data['district'], $subtotal - $promotion['discount'], array_sum(array_column($lines, 'weight')), $promotion['free_shipping'] || collect($lines)->every(fn ($line) => $line['free_shipping']))));
    }
}
