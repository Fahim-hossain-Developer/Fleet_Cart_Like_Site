@extends('themes.default.layout')
@section('title',__('content.blog'))
@section('content')
<h1 class="mb-8 text-4xl font-semibold">{{ __('content.blog') }}</h1>
<div class="grid gap-10 lg:grid-cols-[1fr_280px]"><div><div class="grid gap-6 md:grid-cols-2">@forelse($posts as $post)<article class="space-y-4 rounded-2xl border bg-white p-6"><p class="text-sm text-stone-500">{{ $post->published_at?->format('d M Y') }}</p><h2 class="text-2xl font-semibold"><a href="{{ route('blog.post',$post->slug) }}">{{ $post->title }}</a></h2><p>{{ strip_tags($post->excerpt??'') }}</p><a class="btn-secondary" href="{{ route('blog.post',$post->slug) }}">{{ __('content.read_more') }}</a></article>@empty<p>{{ __('content.no_posts') }}</p>@endforelse</div><div class="mt-8">{{ $posts->links() }}</div></div>
<aside class="space-y-8"><form method="GET" action="{{ route('blog') }}"><label class="block font-semibold" for="blog-search">{{ __('content.blog_search') }}</label><div class="mt-3 flex gap-2"><input id="blog-search" name="q" value="{{ request('q') }}" maxlength="100" class="min-w-0 rounded-lg border p-3"><button class="btn-primary">{{ __('catalog.search') }}</button></div></form>
<section><h2 class="mb-3 text-lg font-semibold">{{ __('content.post-categories') }}</h2>@foreach($categories as $category)<a class="block min-h-11 py-2" href="{{ route('blog',['category'=>$category->slug]) }}">{{ $category->title }} ({{ $category->posts_count }})</a>@endforeach</section>
@foreach(['recent'=>$recent,'popular'=>$popular] as $type=>$list)<section><h2 class="mb-3 text-lg font-semibold">{{ __('content.'.$type) }}</h2>@foreach($list as $post)<a class="block min-h-11 py-2" href="{{ route('blog.post',$post->slug) }}">{{ $post->title }}</a>@endforeach</section>@endforeach
<section><h2 class="mb-3 text-lg font-semibold">{{ __('catalog.tags') }}</h2><div class="flex flex-wrap gap-2">@foreach($tags as $tag)<a class="rounded-full border px-3 py-2" href="{{ route('blog',['tag'=>$tag]) }}">{{ $tag }}</a>@endforeach</div></section>
@foreach(\App\Domain\Content\Models\Banner::published()->where('position','blog')->orderBy('sort')->get() as $banner)@include('themes.default.content.banner',['banner'=>$banner])@endforeach
@if(\Illuminate\Support\Facades\Route::has('newsletter.subscribe'))@include('themes.default.marketing.newsletter')@endif
</aside></div>
@endsection
