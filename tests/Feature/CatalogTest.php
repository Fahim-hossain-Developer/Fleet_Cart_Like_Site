<?php

use App\Domain\Catalog\Actions\CatalogCsv;
use App\Domain\Catalog\Actions\DuplicateProduct;
use App\Domain\Catalog\Actions\GenerateVariants;
use App\Domain\Catalog\Actions\ProductBadges;
use App\Domain\Catalog\Actions\SaveProduct;
use App\Domain\Catalog\Models\Attribute;
use App\Domain\Catalog\Models\AttributeValue;
use App\Domain\Catalog\Models\Badge;
use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\CatalogImport;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\OptionValue;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductDownload;
use App\Domain\Catalog\Models\ProductOption;
use App\Domain\Catalog\Models\ProductTab;
use App\Domain\Catalog\Models\ProductVariant;
use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Jobs\ImportCatalogCsv;
use App\Livewire\CatalogBrowser;
use App\Models\Admin;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function catalogAdmin(string $role = 'super-admin'): Admin
{
    test()->seed(RolesAndPermissionsSeeder::class);
    $admin = Admin::create(['name' => 'Catalog staff', 'email' => fake()->unique()->safeEmail(), 'password' => 'test-password']);
    $admin->assignRole($role);

    return $admin;
}
it('serves only published visible catalog products and redirects slug history', function () {
    $p = Product::factory()->create(['slug' => 'before']);
    $this->get('/product/before')->assertOk()->assertSee($p->name);
    $p->update(['slug' => 'after']);
    $this->get('/product/before')->assertRedirect('/product/after')->assertStatus(301);
    $p->update(['slug' => 'third']);
    $this->get('/product/before')->assertRedirect('/product/third');
    $p->update(['status' => 'draft']);
    $this->get('/product/third')->assertNotFound();
    $this->get('/product/before')->assertNotFound();
    $hidden = Product::factory()->create(['visibility' => 'hidden']);
    $this->get('/product/'.$hidden->slug)->assertNotFound();
});
it('soft deletes products without discarding their records', function () {
    $p = Product::factory()->create();
    $p->delete();
    $this->assertSoftDeleted($p);
    $this->get('/product/'.$p->slug)->assertNotFound();
});
it('applies price date windows and starts variable price at the lowest active variant', function () {
    $p = Product::factory()->create(['price' => 10000, 'special_price' => 8000, 'special_from' => now()->addDay()]);
    expect($p->currentPrice())->toBe(10000);
    $p->update(['special_from' => now()->subDay(), 'special_to' => now()->addDay()]);
    expect($p->currentPrice())->toBe(8000);
    $p->update(['special_to' => now()->subHour()]);
    expect($p->currentPrice())->toBe(10000);
    $p->update(['type' => 'variable']);
    ProductVariant::factory()->create(['product_id' => $p->id, 'price' => 5000]);
    ProductVariant::factory()->create(['product_id' => $p->id, 'price' => 100, 'is_active' => false]);
    expect($p->refresh()->displayPrice())->toBe(5000);
});
it('clones the complete product graph as a zero-stock draft with unique identifiers', function () {
    Storage::fake('public');
    $p = Product::factory()->create(['views' => 44, 'rating_avg' => 4, 'rating_count' => 5, 'qty' => 9]);
    $category = Category::factory()->create();
    $p->categories()->attach($category);
    $variant = ProductVariant::factory()->create(['product_id' => $p->id, 'sku' => 'ORIGINAL-V', 'qty' => 12]);
    $option = ProductOption::factory()->create(['product_id' => $p->id]);
    OptionValue::factory()->create(['product_option_id' => $option->id]);
    ProductTab::factory()->create(['product_id' => $p->id]);
    ProductDownload::factory()->create(['product_id' => $p->id]);
    $p->addMedia(UploadedFile::fake()->image('test.jpg', 40, 40))->toMediaCollection('images');
    $copy = app(DuplicateProduct::class)->execute($p);
    $second = app(DuplicateProduct::class)->execute($p, true);
    expect($copy->status)->toBe('draft')->and($copy->qty)->toBe(0)->and($copy->views)->toBe(0)->and($copy->rating_count)->toBe(0)
        ->and($copy->slug)->not->toBe($p->slug)->and($second->slug)->not->toBe($copy->slug)->and($copy->variants->first()->qty)->toBe(0)
        ->and($second->qty)->toBe(9)->and($second->variants->first()->qty)->toBe(12)->and($copy->options->first()->values)->toHaveCount(1)
        ->and($copy->tabs)->toHaveCount(1)->and($copy->downloads)->toHaveCount(1)->and($copy->categories)->toHaveCount(1)->and($copy->getMedia('images'))->toHaveCount(1)
        ->and($p->refresh()->qty)->toBe(9)->and($variant->refresh()->sku)->toBe('ORIGINAL-V');
    expect(DB::table('activity_log')->where('event', 'duplicated')->count())->toBe(2);
});
it('generates variant combinations idempotently and respects the combination cap', function () {
    $p = Product::factory()->create(['type' => 'variable']);
    foreach (['Size', 'Color'] as $name) {
        $a = Attribute::factory()->create(['name' => ['en' => $name]]);
        foreach (['One', 'Two'] as $value) {
            $v = AttributeValue::factory()->create(['attribute_id' => $a->id, 'value' => $value]);
            $p->attributeValues()->attach($v);
        }
    }
    expect(app(GenerateVariants::class)->execute($p))->toBe(4)->and(app(GenerateVariants::class)->execute($p))->toBe(0)->and($p->variants()->count())->toBe(4);
    expect($p->fresh()->available())->toBeFalse();
    $p->variants()->first()->update(['qty' => 1]);
    expect($p->fresh()->available())->toBeTrue();
});
it('renders safe global and per-product tabs including category targeting', function () {
    $p = Product::factory()->create();
    $c = Category::factory()->create();
    $p->categories()->attach($c);
    ProductTab::factory()->create(['product_id' => null, 'scope' => 'global', 'category_ids' => [$c->id], 'title' => ['en' => 'Targeted tab'], 'body' => ['en' => '<p>Safe content</p><script>alert(1)</script>']]);
    ProductTab::factory()->create(['product_id' => $p->id, 'title' => ['en' => 'Own tab']]);
    ProductTab::factory()->create(['product_id' => null, 'scope' => 'global', 'category_ids' => [999], 'title' => ['en' => 'Wrong tab']]);
    $this->get('/product/'.$p->slug)->assertSee('Targeted tab')->assertSee('Own tab')->assertDontSee('Wrong tab')->assertDontSee('<script>alert(1)</script>', false);
});
it('limits priority ordered badges and honors manual scheduling/category assignment', function () {
    $p = Product::factory()->create(['price' => 10000, 'special_price' => 7500, 'qty' => 0]);
    $c = Category::factory()->create();
    $p->categories()->attach($c);
    $badge = Badge::factory()->create(['priority' => 1, 'label' => ['en' => 'Handmade']]);
    $badge->categories()->attach($c);
    $expired = Badge::factory()->create(['priority' => 0, 'label' => ['en' => 'Expired'], 'ends_at' => now()->subDay()]);
    $expired->products()->attach($p);
    $badges = app(ProductBadges::class)->for($p);
    expect($badges)->toHaveCount(2)->and($badges[0]['label'])->toBe('Out of stock')->and($badges[1]['label'])->toBe('Handmade');
});
it('renders nested category/brand pages and rejects inactive taxonomies', function () {
    $c = Category::factory()->create();
    $b = Brand::factory()->create();
    $this->get('/category/'.$c->slug)->assertOk();
    $this->get('/brand/'.$b->slug)->assertOk();
    $c->update(['is_active' => false]);
    $this->get('/category/'.$c->slug)->assertNotFound();
});
it('loads quick view only for public products and scopes variant choice to that product', function () {
    $p = Product::factory()->create(['type' => 'variable']);
    $v = ProductVariant::factory()->create(['product_id' => $p->id, 'price' => 30000]);
    $draft = Product::factory()->create(['status' => 'draft']);
    Livewire::test(CatalogBrowser::class)->call('quickView', $p->id)->assertSee($p->name)->set('variant', (string) $v->id)->assertSee('300.00')->call('closeQuickView')->assertSet('quickId', null);
    Livewire::test(CatalogBrowser::class)->call('quickView', $draft->id)->assertNotFound();
});
it('validates CSV rows without overwriting originals and neutralizes spreadsheet formulas', function () {
    $path = tempnam(sys_get_temp_dir(), 'catalog-csv');
    file_put_contents($path, "sku,slug,name_en,name_bn,price,qty,status\nCSV-1,csv-one,Imported,আমদানি,12500,4,active\nCSV-2,csv-two,Bad,,oops,4,draft\n");
    $report = app(CatalogCsv::class)->import($path);
    expect($report['imported'])->toBe(1)->and($report['errors'])->toHaveCount(1)->and($report['errors'][0]['row'])->toBe(3);
    $repeat = app(CatalogCsv::class)->import($path);
    expect($repeat['imported'])->toBe(0)->and(Product::where('sku', 'CSV-1')->count())->toBe(1);
    unlink($path);
    expect(CatalogCsv::safeCell('=HYPERLINK("bad")'))->toStartWith("'");
});
it('runs the queued importer and records its report', function () {
    Storage::fake('local');
    $admin = catalogAdmin();
    Storage::disk('local')->put('catalog-imports/a.csv', "sku,slug,name_en,name_bn,price,qty,status\nJOB-1,job-one,Job product,,100,2,draft\n");
    $import = CatalogImport::create(['admin_id' => $admin->id, 'path' => 'catalog-imports/a.csv']);
    (new ImportCatalogCsv($import->id))->handle(app(CatalogCsv::class));
    expect($import->refresh()->status)->toBe('done')->and($import->imported)->toBe(1);
});
it('protects all catalog admin resources, import, preferences and exports', function () {
    $support = catalogAdmin('support');
    $this->actingAs($support, 'admin');
    foreach (['/admin/products', '/admin/categories', '/admin/brands', '/admin/tags', '/admin/attributes', '/admin/attribute-sets', '/admin/product-tabs', '/admin/badges', '/admin/catalog-imports', '/admin/catalog-preferences', '/admin/catalog-export'] as $path) {
        $this->get($path)->assertForbidden();
    }
    $this->flushSession();
    $manager = catalogAdmin('manager');
    $this->actingAs($manager, 'admin');
    foreach (['/admin/products', '/admin/categories', '/admin/brands', '/admin/tags', '/admin/attributes', '/admin/attribute-sets', '/admin/product-tabs', '/admin/badges', '/admin/catalog-imports', '/admin/catalog-preferences', '/admin/catalog-export'] as $path) {
        $response = $this->get($path);
        $response->assertOk();
    }
});
it('creates and edits a product through Filament with validated prices and translations', function () {
    $this->actingAs(catalogAdmin(), 'admin');
    Livewire::test(CreateProduct::class)->fillForm(['name' => ['en' => 'Admin product', 'bn' => 'অ্যাডমিন পণ্য'], 'sku' => 'ADMIN-1', 'cost' => 1200, 'slug' => 'admin-product', 'type' => 'simple', 'status' => 'active', 'visibility' => 'visible', 'price' => 12500, 'qty' => 5, 'stock_status' => 'in_stock'])->call('create')->assertHasNoFormErrors();
    $p = Product::where('sku', 'ADMIN-1')->firstOrFail();
    Livewire::test(EditProduct::class, ['record' => $p->id])->fillForm(['price' => 15000])->call('save')->assertHasNoFormErrors();
    expect($p->refresh()->price)->toBe(15000)->and($p->cost)->toBe(1200)->and($p->getTranslation('name', 'bn'))->toBe('অ্যাডমিন পণ্য');
});
it('keeps digital files private and generates modern media conversions', function () {
    Storage::fake('public');
    Storage::fake('local');
    $p = Product::factory()->create(['type' => 'digital']);
    ProductDownload::factory()->create(['product_id' => $p->id, 'file' => 'product-downloads/private.txt']);
    $media = $p->addMedia(UploadedFile::fake()->image('photo.jpg', 100, 100))->withCustomProperties(['alt' => 'Original alt'])->toMediaCollection('images');
    expect($media->hasGeneratedConversion('card'))->toBeTrue()->and($media->hasGeneratedConversion('modern'))->toBeTrue();
    $this->get('/product/'.$p->slug)->assertOk()->assertDontSee('product-downloads/private.txt');
});
it('remembers recently viewed public products and renders Bangla content', function () {
    $a = Product::factory()->create(['name' => ['en' => 'First item', 'bn' => 'প্রথম পণ্য']]);
    $b = Product::factory()->create();
    $this->get('/product/'.$a->slug);
    $this->get('/product/'.$b->slug)->assertSee('First item');
    $this->post('/locale', ['locale' => 'bn']);
    $this->get('/product/'.$a->slug)->assertSee('প্রথম পণ্য');
});

it('rejects category ancestry cycles', function () {
    $a = Category::factory()->create();
    $b = Category::factory()->create(['parent_id' => $a->id]);
    expect(fn () => $a->update(['parent_id' => $b->id]))->toThrow(ValidationException::class);
});

it('rejects invalid prices and duplicate identifiers in the product action', function () {
    $product = Product::factory()->create();
    expect(fn () => app(SaveProduct::class)->execute(['name' => ['en' => 'Invalid'], 'sku' => $product->sku, 'slug' => 'new-slug', 'price' => -1, 'qty' => 2, 'status' => 'draft', 'type' => 'simple']))->toThrow(ValidationException::class);
});

it('denies anonymous catalog exports without requiring a customer login route', function () {
    $this->get('/admin/catalog-export')->assertForbidden();
});

it('duplicates from the list row, bulk selection and edit header', function () {
    $this->actingAs(catalogAdmin(), 'admin');
    $product = Product::factory()->create();
    Livewire::test(ListProducts::class)
        ->callTableAction('duplicate', $product, ['keep_stock' => false])->assertHasNoTableActionErrors();
    Livewire::test(ListProducts::class)
        ->callTableBulkAction('duplicate', [$product])->assertHasNoTableBulkActionErrors();
    Livewire::test(EditProduct::class, ['record' => $product->id])->callAction('duplicate')->assertHasNoActionErrors();
    expect(Product::where('status', 'draft')->count())->toBe(3)->and(Product::count())->toBe(4);
});

it('lets products suppress selected global tabs without disabling them elsewhere', function () {
    $product = Product::factory()->create();
    $other = Product::factory()->create();
    $tab = ProductTab::factory()->create(['scope' => 'global', 'product_id' => null, 'title' => ['en' => 'Global warranty']]);
    $product->hiddenTabs()->attach($tab);
    $this->get('/product/'.$product->slug)->assertDontSee('Global warranty');
    $this->get('/product/'.$other->slug)->assertSee('Global warranty');
});

it('bulk updates price, stock and categories from the authorized admin table', function () {
    $this->actingAs(catalogAdmin(), 'admin');
    $product = Product::factory()->create();
    $category = Category::factory()->create();
    Livewire::test(ListProducts::class)
        ->callTableBulkAction('update_catalog', [$product], ['price' => 0, 'qty' => 4, 'categories' => [$category->id]])->assertHasNoTableBulkActionErrors();
    expect($product->refresh()->price)->toBe(0)->and($product->qty)->toBe(4)->and($product->categories()->whereKey($category->id)->exists())->toBeTrue();
});
