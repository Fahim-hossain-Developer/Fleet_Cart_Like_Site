<?php

namespace App\Livewire;

use App\Domain\Catalog\Models\Product;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class ProductQuickView extends Component
{
    #[Locked]
    public ?int $productId = null;

    public string $variant = '';

    public int $quantity = 1;

    #[On('open-product-quickview')]
    public function open(int $productId): void
    {
        Product::published()->findOrFail($productId);
        $this->productId = $productId;
        $this->variant = '';
        $this->quantity = 1;
    }

    public function close(): void
    {
        $this->productId = null;
        $this->variant = '';
    }

    public function render(): View
    {
        $quick = $this->productId ? Product::published()->with(['media', 'variants'])->find($this->productId) : null;

        return view('livewire.product-quick-view', ['quick' => $quick, 'selected' => $quick?->variants->where('is_active', true)->firstWhere('id', (int) $this->variant)]);
    }
}
