<?php

namespace App\Livewire;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductTab;
use App\Domain\Catalog\Models\ProductVariant;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ProductDetails extends Component
{
    #[Locked]
    public int $productId;

    public string $variant = '';

    public int $quantity = 1;

    public function mount(int $productId): void
    {
        $this->productId = $productId;
        $id = request()->query('variant');
        if (is_string($id) && ctype_digit($id) && ProductVariant::where('product_id', $productId)->where('is_active', true)->whereKey((int) $id)->exists()) {
            $this->variant = $id;
        }
    }

    public function render(): View
    {
        $product = Product::published()->with(['media', 'variants', 'categories', 'options.values', 'relations.related'])->findOrFail($this->productId);
        $tabs = ProductTab::where('is_active', true)->whereNotIn('id', $product->hiddenTabs()->allRelatedIds())->where(function ($q) use ($product): void {
            $q->where('product_id', $product->id)->orWhere(function ($q) use ($product): void {
                $q->where('scope', 'global')->where(function ($q) use ($product): void {
                    $q->whereNull('category_ids')->orWhereJsonLength('category_ids', 0);
                    foreach ($product->categories as $c) {
                        $q->orWhereJsonContains('category_ids', (string) $c->id)->orWhereJsonContains('category_ids', $c->id);
                    }
                });
            });
        })->orderBy('sort')->get();
        $related = Product::published()->where('id', '!=', $product->id)->where(function ($q) use ($product): void {
            $q->whereIn('id', $product->relations->pluck('related_id'))->orWhereHas('categories', fn ($q) => $q->whereIn('categories.id', $product->categories->modelKeys()));
        })->with(['media', 'categories', 'variants'])->limit(4)->get();
        $recent = Product::published()->whereIn('id', array_slice((array) session('recent_products', []), 0, 8))->where('id', '!=', $product->id)->with(['media', 'categories', 'variants'])->get();

        return view('livewire.product-details', ['product' => $product, 'tabs' => $tabs, 'related' => $related, 'recent' => $recent, 'selected' => $product->variants->where('is_active', true)->firstWhere('id', (int) $this->variant)]);
    }
}
