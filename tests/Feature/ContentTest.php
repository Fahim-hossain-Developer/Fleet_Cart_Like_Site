<?php

use App\Domain\Catalog\Models\Product;
use App\Domain\Content\Actions\SafeContent;
use App\Domain\Content\Actions\SectionCatalog;
use App\Domain\Content\Models\ContactMessage;
use App\Domain\Content\Models\FaqEntry;
use App\Domain\Content\Models\HomeSection;
use App\Domain\Content\Models\Menu;
use App\Domain\Content\Models\MenuItem;
use App\Domain\Content\Models\Page;
use App\Domain\Content\Models\Post;
use App\Domain\Content\Models\PostCategory;
use App\Filament\Resources\PageResource\Pages\ManageRecords;
use Database\Seeders\ContentSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

it('publishes translated pages and posts only within their enabled publication windows', function () {
    $p = Page::factory()->create(['slug' => 'about', 'title' => ['en' => 'About this store', 'bn' => 'এই দোকান সম্পর্কে']]);
    $this->get('/page/about')->assertOk()->assertSee('About this store');
    $p->update(['status' => 'draft']);
    $this->get('/page/about')->assertNotFound();
    $p->update(['status' => 'published', 'published_at' => now()->addHour()]);
    $this->get('/page/about')->assertNotFound();
    $p->update(['published_at' => now()->subHour(), 'ends_at' => now()->subMinute(), 'starts_at' => now()->subDay()]);
    $this->get('/page/about')->assertNotFound();
    $category = PostCategory::factory()->create();
    $post = Post::factory()->create(['post_category_id' => $category->id, 'tags' => ['care']]);
    $this->get('/blog?tag=care')->assertOk()->assertSee($post->title);
    $this->get('/blog/'.$post->slug)->assertOk();
    expect($post->refresh()->views)->toBe(1);
    $category->update(['status' => 'draft']);
    $this->get('/blog/'.$post->slug)->assertNotFound();
    $this->get('/blog')->assertDontSee($post->title);
});
it('sanitizes rich content and rejects unsafe links media and menu cycles', function () {
    $p = Page::factory()->create(['body' => ['en' => '<h2>Safe heading</h2><script>evil()</script><img src=x onerror="evil()"><a href="javascript:evil()">Unsafe</a>']]);
    $this->get('/page/'.$p->slug)->assertOk()->assertSee('Safe heading')->assertDontSee('<script>evil()', false)->assertDontSee('onerror=', false)->assertDontSee('href="javascript:', false);
    foreach (['javascript:alert(1)', '//evil.test', 'https://user:password@example.com', '/\\evil.test'] as $link) {
        expect(SafeContent::link($link))->toBeFalse();
    }
    expect(SafeContent::image('../private.env'))->toBeFalse()->and(SafeContent::image('content/photo.svg'))->toBeFalse();
    $menu = Menu::factory()->create(['key' => 'header']);
    $a = MenuItem::factory()->create(['menu_id' => $menu->id, 'title' => ['en' => 'Catalog'], 'url' => '/shop']);
    $b = MenuItem::factory()->create(['menu_id' => $menu->id, 'parent_id' => $a->id]);
    expect(fn () => $a->update(['parent_id' => $b->id]))->toThrow(ValidationException::class);
    $this->get('/')->assertOk()->assertSee('Catalog');
});
it('renders actual home section data and validates typed blocks', function () {
    $product = Product::factory()->create();
    HomeSection::factory()->create(['type' => 'product_carousel', 'title' => ['en' => 'Selected essentials'], 'config' => ['product_ids' => [$product->id], 'source' => 'new']]);
    HomeSection::factory()->create(['type' => 'rich_text', 'sort' => 1, 'body' => ['en' => '<p>Original editorial copy.</p>']]);
    HomeSection::factory()->create(['type' => 'rich_text', 'status' => 'draft', 'title' => ['en' => 'Private draft marker']]);
    $this->get('/')->assertOk()->assertSee('Selected essentials')->assertSee($product->name)->assertSee('Original editorial copy.')->assertDontSee('Private draft marker');
    expect(fn () => HomeSection::factory()->create(['type' => 'raw_script', 'config' => []]))->toThrow(ValidationException::class);
    expect(fn () => HomeSection::factory()->create(['type' => 'social_grid', 'config' => ['items' => [['url' => 'javascript:evil()']]]]))->toThrow(ValidationException::class);
});
it('supports staff content CRUD and signed draft previews with separate permissions', function () {
    $editor = catalogAdmin('content-editor');
    $this->actingAs($editor, 'admin');
    $p = Page::factory()->create(['status' => 'draft']);
    $this->get('/admin/pages')->assertOk();
    $this->get(URL::temporarySignedRoute('content.preview', now()->addMinutes(5), ['kind' => 'pages', 'id' => $p->id]))->assertOk()->assertSee('Staff preview.')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    Livewire::test(ManageRecords::class)->callAction('create', data: ['title' => ['en' => 'New authored page', 'bn' => 'নতুন পাতা'], 'body' => ['en' => '<p>Fresh content</p>'], 'slug' => 'new-authored-page', 'template' => 'standard', 'status' => 'published', 'is_active' => true, 'sort' => 1])->assertHasNoActionErrors();
    $this->get('/page/new-authored-page')->assertOk()->assertSee('Fresh content');
    $this->flushSession();
    $this->actingAs(catalogAdmin('support'), 'admin')->get('/admin/pages')->assertForbidden();
    $this->get(URL::temporarySignedRoute('content.preview', now()->addMinutes(5), ['kind' => 'pages', 'id' => $p->id]))->assertForbidden();
});
it('retains content slug history and renders Bangla menus and FAQ', function () {
    $p = Page::factory()->create(['slug' => 'old-about']);
    $p->update(['slug' => 'new-about']);
    expect(DB::table('redirects')->where('from', '/page/old-about')->value('to'))->toBe('/page/new-about');
    FaqEntry::factory()->create(['title' => ['en' => 'How to order?', 'bn' => 'কীভাবে অর্ডার করব?'], 'body' => ['en' => 'Use checkout.', 'bn' => 'চেকআউট ব্যবহার করুন।']]);
    $this->withSession(['locale' => 'bn'])->get('/faq')->assertOk()->assertSee('কীভাবে অর্ডার করব?');
});

it('records consented enquiries privately and restricts the inbox from content-only staff', function () {
    $this->post('/contact', ['name' => 'Shopper', 'email' => 'customer@example.test', 'message' => 'PRIVATE_ENQUIRY_SENTINEL', 'consent' => 1])->assertRedirect('/contact');
    $message = ContactMessage::firstOrFail();
    expect($message->message)->toBe('PRIVATE_ENQUIRY_SENTINEL')->and(DB::table('contact_messages')->value('message'))->not->toContain('PRIVATE_ENQUIRY_SENTINEL')->and($message->toJson())->not->toContain('PRIVATE_ENQUIRY_SENTINEL');
    $this->post('/contact', ['name' => 'Bot', 'email' => 'bot@example.test', 'message' => 'Another enquiry', 'consent' => 1, 'website' => 'spam'])->assertSessionHasErrors('website');
    $this->actingAs(catalogAdmin('support'), 'admin')->get('/admin/contact-messages')->assertOk();
    $this->flushSession();
    $this->actingAs(catalogAdmin('content-editor'), 'admin')->get('/admin/contact-messages')->assertForbidden();
});

it('seeds starter content once and preserves owner edits and intentional removals', function () {
    $this->seed(ContentSeeder::class);
    $about = Page::where('slug', 'about')->firstOrFail();
    $about->update(['title' => ['en' => 'Owner-authored title']]);
    $link = MenuItem::firstOrFail();
    $id = $link->id;
    $link->delete();
    $this->seed(ContentSeeder::class);
    expect($about->refresh()->title)->toBe('Owner-authored title')->and(MenuItem::find($id))->toBeNull();
    $this->get('/page/terms')->assertNotFound();
});
it('renders every supported home block without exposing scripts or draft products', function () {
    $p = Product::factory()->create();
    $draft = Product::factory()->create(['status' => 'draft', 'name' => ['en' => 'Unpublished sentinel']]);
    foreach (SectionCatalog::TYPES as $index => $type) {
        $config = ['items' => [['title' => ['en' => 'Original local item'], 'body' => ['en' => 'Authored content'], 'url' => '/shop', 'price' => 25000]]];
        if (in_array($type, ['product_carousel', 'product_tabs'])) {
            $config = ['product_ids' => [$p->id, $draft->id]];
        }
        if ($type === 'countdown') {
            $config = ['ends_at' => now()->addHour()->toIso8601String()];
        }
        HomeSection::factory()->create(['type' => $type, 'sort' => $index, 'title' => ['en' => 'Section '.$type], 'body' => ['en' => '<p>Allowed text</p><script>unsafeBlockMarker()</script>'], 'config' => $config]);
    }
    $response = $this->get('/')->assertOk()->assertDontSee('Unpublished sentinel')->assertDontSee('<script>unsafeBlockMarker()', false);
    foreach (SectionCatalog::TYPES as $type) {
        $response->assertSee('Section '.$type);
    }
});
