<?php

use App\Domain\Catalog\Models\Product;
use App\Domain\Settings\Models\Setting;
use App\Domain\Settings\Models\ThemeDraft;
use App\Domain\Settings\Models\ThemePreset;
use App\Domain\Settings\Theme\Theme;
use App\Domain\Settings\Theme\ThemeConfig;
use App\Filament\Pages\CustomizeAppearance;
use App\Livewire\ProductQuickView;
use Database\Seeders\ThemePresetSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

it('publishes validated appearance with revision checks and preserves unrelated settings', function () {
    $this->actingAs(catalogAdmin('manager'), 'admin');
    $original = app(Theme::class)->state();
    $config = array_replace($original['config'], ['primary' => '#123456', 'header' => 'centered', 'card' => 'second_image']);
    expect(app(Theme::class)->publish($config, $original['revision']))->toBe(1);
    Cache::forget('store.setting.appearance_theme');
    expect(app(Theme::class)->current()['primary'])->toBe('#123456');
    expect(fn () => app(Theme::class)->publish($config, 0))->toThrow(ValidationException::class);
    $this->get('/')->assertOk()->assertSee('data-theme-header="centered"', false)->assertSee('--theme-primary:#123456', false);
});
it('rejects executable CSS paths unknown layouts and unreadable backgrounds', function () {
    $config = app(ThemeConfig::class)->defaults();
    foreach ([['primary' => 'red; background:url(https://invalid.test)'], ['header' => '../../private'], ['background' => '#000000'], ['font' => 'remote-font'], ['radius' => 100]] as $bad) {
        expect(fn () => app(ThemeConfig::class)->validate(array_replace($config, $bad)))->toThrow(ValidationException::class);
    }
    expect(app(ThemeConfig::class)->css(array_replace($config, ['primary' => '#ffff00'])))->toContain('--theme-on-primary:#000000');
});
it('keeps draft previews private expiring owner bound and does not publish on preview', function () {
    $a = catalogAdmin('manager');
    $this->actingAs($a, 'admin');
    $config = array_replace(app(ThemeConfig::class)->defaults(), ['header' => 'mega', 'primary' => '#312e81']);
    $draft = app(Theme::class)->draft($config, 0);
    $url = app(Theme::class)->previewUrl($draft);
    $this->get($url)->assertOk()->assertSee('data-theme-header="mega"', false)->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertHeader('Cache-Control');
    expect($this->get($url)->headers->get('Cache-Control'))->toContain('no-store')->toContain('private');
    expect(Setting::where('key', 'appearance_theme')->exists())->toBeFalse();
    $this->get('/')->assertOk()->assertSee('data-theme-header="classic"', false);
    $this->actingAs(catalogAdmin('manager'), 'admin')->get($url)->assertNotFound();
    $this->actingAs($a, 'admin');
    $draft->update(['expires_at' => now()->subMinute()]);
    $this->get($url)->assertNotFound();
});
it('uses the real customizer form for preview publish and reusable preset creation', function () {
    $this->actingAs(catalogAdmin('manager'), 'admin');
    Livewire::test(CustomizeAppearance::class)->fillForm(array_replace(app(ThemeConfig::class)->defaults(), ['footer' => 'newsletter', 'listing' => 'top']))->call('previewTheme')->assertHasNoFormErrors()->assertSet('revision', 0)->call('publish')->assertHasNoFormErrors()->assertSet('revision', 1)->set('presetName', 'Original sky')->call('savePreset')->assertHasNoFormErrors();
    expect(ThemeDraft::count())->toBe(1)->and(ThemePreset::count())->toBe(1);
    $this->actingAs(catalogAdmin('support'), 'admin')->get('/admin/customize-appearance')->assertForbidden();
});
it('renders all preset layouts while retaining public products and checkout controls', function () {
    $this->seed(ThemePresetSeeder::class);
    expect(ThemePreset::count())->toBe(6);
    $this->actingAs(catalogAdmin(), 'admin');
    $p = Product::factory()->create();
    foreach (ThemePreset::all() as $preset) {
        $draft = app(Theme::class)->draft($preset->config, 0);
        $this->get(app(Theme::class)->previewUrl($draft, 'shop'))->assertOk()->assertSee($p->name)->assertSee('data-listing-layout="'.$preset->config['listing'].'"', false);
    }
    $this->get(route('product', $p->slug))->assertOk()->assertSee('name="product_id"', false);
    $this->get('/cart')->assertOk();
});
it('opens real global quick views for cards outside listing pages and rejects private products', function () {
    $product = Product::factory()->create(['price' => 12345]);
    Livewire::test(ProductQuickView::class)->call('open', $product->id)->assertSee($product->name)->assertSee('123.45')->call('close')->assertSet('productId', null);
    $product->update(['status' => 'draft']);
    expect(fn () => Livewire::test(ProductQuickView::class)->call('open', $product->id))->toThrow(ModelNotFoundException::class);
});
