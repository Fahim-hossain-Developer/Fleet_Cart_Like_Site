<?php

namespace App\Domain\Checkout\Landing;

use App\Domain\Cart\Actions\CartManager;
use App\Domain\Cart\Models\Cart;
use App\Domain\Content\Models\LandingPage;
use App\Domain\Content\Models\ProductPackage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class LandingCart
{
    public function current(LandingPage $page): Cart
    {
        $key = 'landing_cart.'.$page->id;
        $token = session($key);
        $cart = is_string($token) ? Cart::where('token', $token)->where('landing_page_id', $page->id)->first() : null;
        if (! $cart) {
            $cart = Cart::create(['token' => Str::random(64), 'landing_page_id' => $page->id]);
            session([$key => $cart->token]);
        }

        return $cart;
    }

    /** @param array<string,mixed> $options */
    public function prepare(LandingPage $page, Cart $cart, int $packageId, int $units, bool $bump, array $options = []): void
    {
        validator(compact('units'), ['units' => 'integer|min:1|max:10'])->validate();
        DB::transaction(function () use ($page, $cart, $packageId, $units, $bump, $options) {
            Cart::lockForUpdate()->where('landing_page_id', $page->id)->findOrFail($cart->id);
            $package = ProductPackage::where('landing_page_id', $page->id)->where('role', 'primary')->where('is_active', true)->findOrFail($packageId);
            $selected = [[$package, $units, $options]];
            $bumpId = null;
            if ($bump && ! empty($page->form_config['bump_package_id'])) {
                $extra = ProductPackage::where('landing_page_id', $page->id)->where('role', 'bump')->where('is_active', true)->findOrFail($page->form_config['bump_package_id']);
                $bumpId = $extra->id;
                if ($extra->id === $package->id) {
                    abort(422);
                }$selected[] = [$extra, 1, []];
            }
            // Dedicated landing cart: the shopper's ordinary cart and saved items are preserved.
            $cart->items()->delete();
            foreach ($selected as [$p,$count,$choices]) {
                app(CartManager::class)->add($cart, $p->product_id, $p->variant_id, $p->qty * $count, $choices);
                $item = $cart->items()->where('product_id', $p->product_id)->where('variant_id', $p->variant_id)->latest('id')->firstOrFail();
                $item->update(['line_key' => hash('sha256', 'landing:'.$p->id.':'.$count), 'product_package_id' => $p->id, 'package_units' => $count]);
            }
            $cart->update(['landing_context' => ['package_id' => $package->id, 'units' => $units, 'bump_package_id' => $bumpId]]);
        }, 3);
    }
}
