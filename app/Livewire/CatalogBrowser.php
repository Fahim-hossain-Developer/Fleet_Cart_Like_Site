<?php

namespace App\Livewire;

use App\Domain\Catalog\Actions\CatalogPrice;
use App\Domain\Catalog\Actions\CatalogSearch;
use App\Domain\Catalog\Models\Attribute;
use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Tag;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class CatalogBrowser extends Component
{
    use WithPagination;

    #[Url]
    public string $q = '';

    #[Url]
    public string $category = '';

    #[Url]
    public string $brand = '';

    #[Url]
    public string $tag = '';

    #[Url]
    public string $sort = 'newest';

    #[Url]
    public int $perPage = 24;

    #[Url]
    public int $columns = 3;

    #[Url]
    public string $display = 'grid';

    #[Url]
    public string $min = '';

    #[Url]
    public string $max = '';

    #[Url]
    public bool $inStock = false;

    #[Url]
    public bool $onSale = false;

    /** @var list<string> */
    #[Url(as: 'attributes')]
    public array $attributeFilters = [];

    #[Locked]
    public ?int $routeCategory = null;

    #[Locked]
    public ?int $routeBrand = null;

    #[Locked]
    public ?int $quickId = null;

    public string $variant = '';

    public int $quantity = 1;

    public function mount(?string $categorySlug = null, ?string $brandSlug = null): void
    {
        if ($categorySlug) {
            $this->routeCategory = Category::where('slug', $categorySlug)->where('is_active', true)->firstOrFail()->id;
        }
        if ($brandSlug) {
            $this->routeBrand = Brand::where('slug', $brandSlug)->where('is_active', true)->firstOrFail()->id;
        }
    }

    public function updated(string $name): void
    {
        if (! in_array($name, ['variant', 'quantity'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('q', 'category', 'brand', 'sort', 'min', 'max', 'inStock', 'onSale', 'attributeFilters', 'tag');
        $this->resetPage();
    }

    public function quickView(int $id): void
    {
        abort_unless(Product::published()->whereKey($id)->exists(), 404);
        $this->quickId = $id;
        $this->variant = '';
        $this->quantity = 1;
    }

    public function closeQuickView(): void
    {
        $this->quickId = null;
    }

    public function render(): View
    {
        $query = app(CatalogSearch::class)->apply(Product::published()->with(['brand', 'categories', 'media', 'variants']), $this->q);
        $priceSql = app(CatalogPrice::class)->expression();
        $query->select('products.*')->selectRaw($priceSql.' AS catalog_price');
        $category = $this->routeCategory ?: ($this->category !== '' ? (int) $this->category : null);
        if ($category) {
            $ids = [$category];
            $frontier = [$category];
            while ($frontier) {
                $frontier = Category::whereIn('parent_id', $frontier)->where('is_active', true)->whereNotIn('id', $ids)->pluck('id')->all();
                $ids = array_merge($ids, $frontier);
            }
            $query->whereHas('categories', fn ($q) => $q->whereIn('categories.id', $ids));
        }
        if ($this->routeBrand || $this->brand !== '') {
            $query->where('brand_id', $this->routeBrand ?: (int) $this->brand);
        }
        if ($this->tag !== '') {
            $query->whereHas('tags', fn ($q) => $q->where('tags.id', (int) $this->tag));
        }
        if ($this->min !== '' && ctype_digit($this->min)) {
            $query->whereRaw('('.$priceSql.') >= ?', [min((int) $this->min, 1000000000) * 100]);
        }
        if ($this->max !== '' && ctype_digit($this->max)) {
            $query->whereRaw('('.$priceSql.') <= ?', [min((int) $this->max, 1000000000) * 100]);
        }
        if ($this->inStock) {
            $query->where(function ($q): void {
                $q->where(fn ($q) => $q->where('type', '!=', 'variable')->where('stock_status', '!=', 'out_of_stock')->where(fn ($q) => $q->where('manage_stock', false)->orWhere('qty', '>', 0)->orWhere('stock_status', 'backorder')))->orWhere(fn ($q) => $q->where('type', 'variable')->whereHas('variants', fn ($q) => $q->where('is_active', true)->where('qty', '>', 0)));
            });
        }
        if ($this->onSale) {
            $query->whereNotNull('special_price')->whereColumn('special_price', '<', 'price')->where(fn ($q) => $q->whereNull('special_from')->orWhere('special_from', '<=', now()))->where(fn ($q) => $q->whereNull('special_to')->orWhere('special_to', '>=', now()));
        }
        foreach (array_slice($this->attributeFilters, 0, 20) as $value) {
            $query->whereHas('attributeValues', fn ($q) => $q->where('attribute_values.id', (int) $value));
        }
        match ($this->sort) {
            'price_asc' => $query->orderBy('catalog_price'), 'price_desc' => $query->orderByDesc('catalog_price'),'name' => $query->orderBy('name'),'rating' => $query->orderByDesc('rating_avg'),default => $query->latest('id')
        };
        $products = $query->paginate(in_array($this->perPage, [12, 24, 48, 96], true) ? $this->perPage : 24);
        $quick = $this->quickId ? Product::published()->with(['media', 'variants', 'categories'])->findOrFail($this->quickId) : null;
        $selected = $quick?->variants->firstWhere('id', (int) $this->variant);

        return view('livewire.catalog-browser', ['products' => $products, 'categories' => Category::where('is_active', true)->orderBy('sort')->get(), 'brands' => Brand::where('is_active', true)->get(), 'filterAttributes' => Attribute::where('is_filterable', true)->with('values')->get(), 'topRated' => Product::published()->where('rating_count', '>', 0)->orderByDesc('rating_avg')->limit(3)->get(), 'topTags' => Tag::where('is_active', true)->get(), 'latest' => Product::published()->latest('id')->limit(3)->get(), 'saleItems' => Product::published()->whereNotNull('special_price')->limit(3)->get(), 'quick' => $quick, 'selected' => $selected]);
    }
}
