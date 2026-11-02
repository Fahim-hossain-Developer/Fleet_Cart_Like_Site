<?php

use App\Domain\Catalog\Models\Product;
use App\Domain\Content\Models\Page;
use App\Domain\Content\Models\Popup;
use App\Domain\Content\Models\Post;
use App\Domain\Content\Models\PostCategory;
use App\Domain\Content\Models\Redirect;
use App\Domain\Marketing\Actions\StorefrontControls;
use App\Domain\Orders\Models\Order;
use App\Domain\Settings\Actions\Settings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

it('publishes split sitemaps and excludes drafts and posts in draft categories', function () {
    $p = Product::factory()->create();
    $draft = Product::factory()->create(['status' => 'draft']);
    $page = Page::factory()->create(['status' => 'published']);
    $hidden = Page::factory()->create(['status' => 'draft']);
    $category = PostCategory::factory()->create(['status' => 'draft']);
    $post = Post::factory()->create(['status' => 'published', 'post_category_id' => $category->id]);
    $this->get('/sitemap.xml')->assertOk();
    $response = $this->get('/sitemap-products-1.xml')->assertOk();
    $xml = $response->streamedContent();
    expect($xml)->toContain('/product/'.$p->slug)->not->toContain($draft->slug);
    expect($this->get('/sitemap-pages-1.xml')->streamedContent())->toContain('/page/'.$page->slug)->not->toContain($hidden->slug);
    expect($this->get('/sitemap-posts-1.xml')->streamedContent())->not->toContain($post->slug);
    $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /account')->assertSee('Sitemap:');
});
it('renders escaped sharing tags and current base prices in structured data without private schema', function () {
    $p = Product::factory()->create(['name' => ['en' => 'Safe </script><script>alert(1)</script> product'], 'price' => 12345]);
    $r = $this->get(route('product', $p->slug))->assertOk()->assertSee('property="og:title"', false)->assertSee('hreflang="bn"', false);
    preg_match('~<script type="application/ld\+json">(.*?)</script>~s', $r->getContent(), $m);
    $data = json_decode($m[1], true, 512, JSON_THROW_ON_ERROR);
    $product = collect($data['@graph'])->firstWhere('@type', 'Product');
    expect($product['offers']['price'])->toBe('123.45')->and($product['offers']['priceCurrency'])->toBe('BDT');
    expect($m[1])->not->toContain('</script>');
    $this->get('/login')->assertOk()->assertSee('noindex,nofollow')->assertDontSee('application/ld+json');
    $this->get(route('product', $p->slug).'?lang=bn')->assertOk()->assertSee('<html lang="bn">', false);
});
it('follows validated redirects and rejects loops and unsafe destinations', function () {
    $page = Page::factory()->create(['status' => 'published']);
    Redirect::create(['from' => '/old-about', 'to' => '/page/'.$page->slug, 'code' => 302]);
    $this->get('/old-about')->assertRedirect('/page/'.$page->slug)->assertStatus(302);
    $old = $page->slug;
    $page->update(['slug' => 'renamed-page']);
    $this->get('/page/'.$old)->assertRedirect('/page/renamed-page');
    expect(fn () => Redirect::create(['from' => '/loop', 'to' => '/loop', 'code' => 301]))->toThrow(ValidationException::class);
    expect(fn () => Redirect::create(['from' => '/bad-link', 'to' => 'javascript:alert(1)', 'code' => 301]))->toThrow(ValidationException::class);
    $page->update(['status' => 'draft']);
    $this->get('/page/'.$old)->assertNotFound();
});
it('shows scheduled popups only on eligible public pages with frequency and focus controls', function () {
    $popup = Popup::factory()->create(['status' => 'published', 'title' => ['en' => 'Optional news'], 'paths' => ['/shop']]);
    $this->get('/shop')->assertOk()->assertSee('store-popup-title')->assertSee('localStorage')->assertSee('x-trap.inert.noscroll', false);
    $this->get('/login')->assertOk()->assertDontSee('store-popup-title');
    $popup->update(['starts_at' => now()->addDay()]);
    $this->get('/shop')->assertOk()->assertDontSee('store-popup-title');
    expect(fn () => $popup->update(['frequency_hours' => 0]))->toThrow(ValidationException::class);
});
it('validates floating targets and never loads chat on sensitive pages or before a click', function () {
    app(Settings::class)->set('floating_controls', ['position' => 'left', 'whatsapp' => ['enabled' => true, 'phone' => '+8801712345678', 'message' => 'Hi'], 'chat' => ['enabled' => true, 'property' => str_repeat('a', 24), 'widget' => 'widget1'], 'back_to_top' => true]);
    $p = Product::factory()->create();
    $this->get(route('product', $p->slug))->assertOk()->assertSee('wa.me/8801712345678')->assertSee('bottom-28')->assertDontSee('<script src="https://embed.tawk.to', false);
    $this->get('/login')->assertOk()->assertDontSee('wa.me')->assertDontSee('embed.tawk.to');
    expect(fn () => app(Settings::class)->set('floating_controls', ['position' => 'right', 'whatsapp' => ['phone' => 'javascript:alert(1)']]))->toThrow(ValidationException::class);
});
it('uses only fully paid recent live fulfilled purchases and exposes no buyer identity', function () {
    app(Settings::class)->set('social_proof', ['enabled' => true, 'frequency' => 60, 'exclude' => []]);
    $product = Product::factory()->create();
    foreach (['sandbox', 'live'] as $mode) {
        $o = Order::factory()->create(['status' => 'delivered', 'payment_status' => 'paid', 'payment_environment' => $mode, 'total' => 10000, 'paid_amount' => 10000, 'shipping_address' => ['name' => 'Private Name', 'district' => ['en' => 'Dhaka']]]);
        $o->items()->create(['product_id' => $product->id, 'name' => $product->name, 'sku' => $product->sku, 'qty' => 1, 'unit_price' => 10000, 'subtotal' => 10000, 'discount' => 0, 'tax' => 0, 'total' => 10000, 'options' => []]);
    }
    $r = $this->get('/shop')->assertOk();
    expect(app(StorefrontControls::class)->proof()['items'])->toHaveCount(1);
    $r->assertDontSee('Private Name')->assertDontSee('+8801712345678');
    Order::where('payment_environment', 'live')->update(['status' => 'cancelled']);
    Cache::flush();
    $this->get('/shop')->assertOk()->assertDontSee('Someone from Dhaka');
});
