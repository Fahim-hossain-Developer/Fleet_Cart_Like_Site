<?php

namespace App\Http\Controllers;

use App\Domain\Content\Actions\ResolveRedirect;
use App\Domain\Content\Models\FaqEntry;
use App\Domain\Content\Models\HomeSection;
use App\Domain\Content\Models\Page;
use App\Domain\Content\Models\Post;
use App\Domain\Content\Models\PostCategory;
use App\Domain\Orders\Actions\OrderAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ContentController extends Controller
{
    public function home(): mixed
    {
        return view('themes.default.home', ['sections' => HomeSection::published()->where(fn ($q) => $q->whereNull('locale')->orWhere('locale', app()->getLocale()))->orderBy('sort')->orderBy('id')->get()]);
    }

    public function page(string $slug): mixed
    {
        $entry = Page::published()->where('slug', $slug)->first();
        if (! $entry) {
            return app(ResolveRedirect::class)->execute('/page/'.$slug) ?? abort(404);
        }

        return view('themes.default.content.page', ['entry' => $entry, 'preview' => false]);
    }

    public function post(string $slug): mixed
    {
        $p = Post::published()->where(fn ($q) => $q->whereNull('post_category_id')->orWhereHas('category', fn ($q) => $q->published()))->where('slug', $slug)->first();
        if (! $p) {
            return app(ResolveRedirect::class)->execute('/blog/'.$slug) ?? abort(404);
        }
        DB::table('posts')->where('id', $p->id)->increment('views');

        return view('themes.default.content.page', ['entry' => $p, 'preview' => false]);
    }

    public function blog(Request $r): mixed
    {
        $d = $r->validate(['q' => 'nullable|string|max:100', 'category' => 'nullable|string|max:160', 'tag' => 'nullable|string|max:50']);
        $q = Post::published()->with('category')->where(fn ($q) => $q->whereNull('post_category_id')->orWhereHas('category', fn ($q) => $q->published()));
        if (! empty($d['q'])) {
            $q->where('title', 'like', '%'.$d['q'].'%');
        }if (! empty($d['category'])) {
            $q->whereHas('category', fn ($q) => $q->where('slug', $d['category'])->published());
        }if (! empty($d['tag'])) {
            $q->whereJsonContains('tags', $d['tag']);
        }
        $all = Post::published()->where(fn ($q) => $q->whereNull('post_category_id')->orWhereHas('category', fn ($q) => $q->published()));

        return view('themes.default.content.blog', ['posts' => $q->latest('published_at')->paginate(12)->withQueryString(), 'categories' => PostCategory::published()->withCount(['posts' => fn ($q) => $q->published()])->get(), 'recent' => (clone $all)->latest('published_at')->limit(5)->get(), 'popular' => (clone $all)->orderByDesc('views')->limit(5)->get(), 'tags' => (clone $all)->limit(100)->pluck('tags')->flatten()->filter()->unique()->take(40)]);
    }

    public function faq(): mixed
    {
        return view('themes.default.content.faq', ['entries' => FaqEntry::published()->orderBy('sort')->get()]);
    }

    public function preview(string $kind, int $id): mixed
    {
        app(OrderAccess::class)->staff('content.manage');
        abort_unless(in_array($kind, ['pages', 'posts'], true), 404);
        $entry = ($kind === 'pages' ? Page::query() : Post::query())->findOrFail($id);

        return response()->view('themes.default.content.page', ['entry' => $entry, 'preview' => true])->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
