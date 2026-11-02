<?php

namespace App\Domain\Cart\Actions;

use App\Domain\Cart\Models\Cart;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Marketing\Actions\FlashPricing;
use Illuminate\Validation\ValidationException;

final class QuoteCart
{
    /**
     * @return list<array<string,mixed>> */
    public function execute(Cart $cart, bool $lock = false): array
    {
        $lines = [];
        $stock = [];
        $productQty = [];
        $runningTotal = 0;
        foreach ($cart->items()->where('saved', false)->orderBy('product_id')->orderBy('variant_id')->get() as $item) {
            $product = Product::published()->when($lock, fn ($q) => $q->lockForUpdate())->find($item->product_id);
            if (! $product) {
                throw ValidationException::withMessages(['cart' => __('checkout.product_unavailable')]);
            }
            $variant = $item->variant_id ? ProductVariant::where('product_id', $product->id)->where('is_active', true)->when($lock, fn ($q) => $q->lockForUpdate())->find($item->variant_id) : null;
            if (($product->type === 'variable') !== ($variant !== null)) {
                throw ValidationException::withMessages(['cart' => __('checkout.choose_variant')]);
            }
            $productQty[$product->id] = ($productQty[$product->id] ?? 0) + $item->qty;
            if ($product->max_per_order && $productQty[$product->id] > $product->max_per_order) {
                throw ValidationException::withMessages(['cart' => __('checkout.quantity_limit')]);
            }
            $stockKey = $product->id.':'.($variant->id ?? 0);
            $stock[$stockKey] = ($stock[$stockKey] ?? 0) + $item->qty;
            app(CartManager::class)->assertStock($product, $variant, $stock[$stockKey]);
            $options = app(OptionPricing::class)->execute($product, $item->options, $cart->token, true);
            $flash = app(FlashPricing::class)->quote($product, $variant, $variant->special_price ?? $variant->price ?? $product->basePrice(), $stock[$stockKey], $lock);
            $unit = $flash['price'];
            $subtotal = ($unit + $options['fixed']) * $item->qty + intdiv($unit * $item->qty * $options['percent'] + 50, 100);
            $runningTotal += $subtotal;
            if ($runningTotal > 100000000000) {
                throw ValidationException::withMessages(['cart' => __('checkout.amount_limit')]);
            }
            $lines[] = ['flash_sale_item_id' => $flash['item']?->id, 'cart_item_id' => $item->id, 'product' => $product, 'variant' => $variant, 'product_id' => $product->id, 'variant_id' => $variant?->id, 'name' => $product->name, 'sku' => $variant->sku ?? $product->sku, 'qty' => $item->qty, 'unit_price' => $unit + $options['fixed'], 'subtotal' => $subtotal, 'options' => ['values' => $options['options'], 'labels' => $options['labels']], 'weight' => $product->weight_grams * $item->qty, 'tax_class_id' => $product->tax_class_id, 'free_shipping' => $product->free_shipping, 'digital' => $product->type === 'digital'];
        }

        return $lines;
    }
}
