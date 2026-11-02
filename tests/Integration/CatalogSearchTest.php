<?php

use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Livewire\CatalogBrowser;
use App\Livewire\HeaderSearch;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

it('scopes searches and filters and supports pagination toolbar state', function () {
    $c = Category::factory()->create();
    $brand = Brand::factory()->create();
    $p = Product::factory()->create(['name' => ['en' => 'Linen test shirt'], 'price' => 12000, 'brand_id' => $brand->id]);
    $p->categories()->attach($c);
    $other = Product::factory()->create(['name' => ['en' => 'Different item'], 'qty' => 0]);
    Livewire::test(CatalogBrowser::class)->set('q', 'Linen')->set('category', (string) $c->id)->set('brand', (string) $brand->id)->set('min', '100')->set('max', '200')->set('inStock', true)->set('sort', 'price_asc')->set('perPage', 12)->set('columns', 4)->set('display', 'list')->assertSee('Linen test shirt')->assertViewHas('products', fn ($products) => $products->total() === 1 && $products->first()->id === $p->id)->call('clearFilters')->assertSet('q', '');
    Livewire::test(HeaderSearch::class)->set('term', 'Linen')->set('category', (string) $c->id)->assertSee('Linen test shirt');
    $this->get('/search?q=Linen')->assertOk();
    expect(DB::table('search_logs')->where('term', 'Linen')->count())->toBe(1);
});

it('filters and sorts by the price actually displayed including sales and variants', function () {
    $sale = Product::factory()->create(['price' => 100000, 'special_price' => 50000]);
    $variable = Product::factory()->create(['type' => 'variable', 'price' => 120000]);
    ProductVariant::factory()->create(['product_id' => $variable->id, 'price' => 40000]);
    Product::factory()->create(['price' => 90000]);
    Livewire::test(CatalogBrowser::class)->set('max', '600')->set('sort', 'price_asc')
        ->assertViewHas('products', fn ($products) => $products->total() === 2 && $products->first()->id === $variable->id);
});
