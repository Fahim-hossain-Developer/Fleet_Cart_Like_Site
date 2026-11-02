<?php

namespace App\Domain\Cart\Actions;

use App\Domain\Cart\Models\Cart;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CartManager
{
    /** @param array<string,mixed> $options */
    public function add(Cart $cart, int $productId, ?int $variantId, int $quantity, array $options = []): void
    {
        validator(['quantity' => $quantity], ['quantity' => 'required|integer|min:1|max:100'])->validate();
        DB::transaction(function () use ($cart, $productId, $variantId, $quantity, $options): void {
            $cart = Cart::query()->lockForUpdate()->findOrFail($cart->id);
            $product = Product::published()->findOrFail($productId);
            $variant = $variantId ? ProductVariant::where('product_id', $product->id)->where('is_active', true)->findOrFail($variantId) : null;
            if (($product->type === 'variable') !== ($variant !== null)) {
                throw ValidationException::withMessages(['variant' => __('checkout.choose_variant')]);
            }
            $values = app(OptionPricing::class)->execute($product, $options, $cart->token);
            $key = hash('sha256', json_encode([$product->id, $variant?->id, $values['options']], JSON_THROW_ON_ERROR));
            $item = $cart->items()->where('line_key', $key)->first();
            $qty = ($item->qty ?? 0) + $quantity;
            if (! $item && $cart->items()->count() >= 100) {
                throw ValidationException::withMessages(['cart' => __('checkout.quantity_limit')]);
            }
            $this->assertStock($product, $variant, $qty);
            $cart->items()->updateOrCreate(['line_key' => $key], ['product_id' => $product->id, 'variant_id' => $variant?->id, 'qty' => $qty, 'options' => $values['options'], 'saved' => false]);
            $cart->touch();
        });
    }

    public function quantity(Cart $cart, int $id, int $quantity): void
    {
        validator(['quantity' => $quantity], ['quantity' => 'required|integer|min:1|max:100'])->validate();
        DB::transaction(function () use ($cart, $id, $quantity): void {
            Cart::query()->lockForUpdate()->findOrFail($cart->id);
            $item = $cart->items()->findOrFail($id);
            $product = Product::published()->findOrFail($item->product_id);
            $variant = $item->variant_id ? ProductVariant::where('product_id', $product->id)->where('is_active', true)->findOrFail($item->variant_id) : null;
            $this->assertStock($product, $variant, $quantity);
            $item->update(['qty' => $quantity]);
        });
    }

    public function remove(Cart $cart, int $id): void
    {
        $cart->items()->findOrFail($id)->delete();
    }

    public function save(Cart $cart, int $id, bool $saved): void
    {
        $cart->items()->findOrFail($id)->update(['saved' => $saved]);
    }

    public function assertStock(Product $product, ?ProductVariant $variant, int $quantity): void
    {
        if ($quantity > 100 || ($product->max_per_order && $quantity > $product->max_per_order) || ($variant->qty ?? ($product->manage_stock && $product->stock_status !== 'backorder' ? $product->qty : 100)) < $quantity || ($variant === null && $product->stock_status === 'out_of_stock')) {
            throw ValidationException::withMessages(['cart' => __('checkout.insufficient_stock', ['name' => $product->name])]);
        }
    }
}
