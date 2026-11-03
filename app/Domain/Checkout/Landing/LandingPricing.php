<?php

namespace App\Domain\Checkout\Landing;

use App\Domain\Cart\Models\Cart;
use App\Domain\Cart\Models\CartItem;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Content\Models\LandingPage;
use App\Domain\Content\Models\ProductPackage;
use Illuminate\Validation\ValidationException;

final class LandingPricing
{
    /** @param array<string,mixed> $options
     * @param array<string,mixed> $flash
     * @return array<string,mixed>|null */
    public function line(Cart $cart, CartItem $item, Product $product, ?ProductVariant $variant, array $options, array $flash, bool $lock): ?array
    {
        if (! $item->package_units && ! $item->product_package_id) {
            return null;
        }
        $page = LandingPage::published()->find($cart->landing_page_id);
        $p = ProductPackage::where('is_active', true)->when($lock, fn ($q) => $q->lockForUpdate())->find($item->product_package_id);
        $context = $cart->landing_context ?? [];
        $main = (int) ($context['package_id'] ?? 0);
        $isMain = $p && $p->id === $main;
        if (! $page || ! $p || $p->product_id !== $product->id || $p->variant_id !== ($variant->id ?? null) || $item->qty !== $p->qty * $item->package_units || ($isMain ? ($p->landing_page_id !== $page->id) : ($p->id !== (int) ($context['bump_package_id'] ?? 0) || $p->id !== (int) ($page->form_config['bump_package_id'] ?? 0))) || ! LandingPage::published()->whereKey($p->landing_page_id)->exists()) {
            throw ValidationException::withMessages(['package' => __('landing.package_unavailable')]);
        }
        $base = $p->price * $item->package_units;
        $useFlash = $flash['item'] && $base > $flash['price'] * $item->qty;
        if ($useFlash) {
            $base = $flash['price'] * $item->qty;
        }
        $subtotal = $base + $options['fixed'] * $item->qty + intdiv($base * $options['percent'] + 50, 100);
        $mainPackage = $isMain ? $p : ProductPackage::find($main);

        return ['subtotal' => $subtotal, 'unit_price' => intdiv($base, $item->qty) + $options['fixed'], 'flash_sale_item_id' => $useFlash ? $flash['item']->id : null, 'free_shipping' => $p->free_shipping || ($mainPackage->free_shipping ?? false), 'package_snapshot' => ['id' => $p->id, 'label' => $p->label, 'qty' => $p->qty, 'units' => $item->package_units, 'package_price' => $p->price, 'charged_subtotal' => $subtotal, 'bump' => ! $isMain, 'free_shipping' => $p->free_shipping]];
    }
}
