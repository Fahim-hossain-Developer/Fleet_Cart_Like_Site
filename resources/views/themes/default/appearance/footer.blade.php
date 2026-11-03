<footer data-theme-footer="{{ $theme['footer'] }}" class="theme-footer border-t border-stone-200 bg-white"><div class="mx-auto max-w-6xl px-5 py-10">
@if($theme['footer']==='newsletter')<div class="mb-8 grid gap-8 md:grid-cols-2"><div><h2 class="text-2xl font-semibold">{{ store_setting('name') }}</h2><p class="mt-4 text-stone-600">{{ __('store.footer') }}</p></div>@include('themes.default.marketing.newsletter')</div>@endif
<div class="flex flex-wrap justify-between gap-6"><a href="{{ route('home') }}" class="font-semibold">{{ store_setting('name') }}</a>@if($theme['footer']!=='newsletter')<p class="text-sm text-stone-600">{{ __('store.footer') }}</p>@endif</div>
@php($footerItems=app(\App\Domain\Content\Actions\Navigation::class)->tree('footer'))
@if($theme['footer']==='columns')<nav class="mt-8 grid grid-cols-2 gap-8 md:grid-cols-4" aria-label="{{ __('content.footer_navigation') }}">@foreach($footerItems as $item)<div><a href="{{ $item['url'] }}" class="inline-flex min-h-11 items-center font-semibold">{{ $item['title'] }}</a><div class="mt-3 space-y-3">@include('themes.default.content.menu',['items'=>$item['children']])</div></div>@endforeach</nav>
@else<nav class="mt-6 flex flex-wrap gap-6" aria-label="{{ __('content.footer_navigation') }}">@include('themes.default.content.menu',['items'=>$footerItems])</nav>@endif
</div></footer>
