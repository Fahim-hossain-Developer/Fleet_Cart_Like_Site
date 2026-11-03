@php($theme=app(\App\Domain\Settings\Theme\Theme::class)->current())
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" style="{{ app(\App\Domain\Settings\Theme\ThemeConfig::class)->css($theme) }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    @include('themes.default.content.seo')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body data-theme-font="{{ $theme['font'] }}" data-theme-section="{{ $theme['section_style'] }}" class="theme-storefront bg-stone-50 text-stone-900 antialiased" x-data="{ menuOpen: false }" @keydown.escape.window="menuOpen = false">
    <a href="#main" class="sr-only focus:not-sr-only focus:block focus:p-4">{{ __('store.skip') }}</a>
    @include('themes.default.appearance.header')
    @if(request()->has('theme_preview'))<div data-theme-preview-banner class="border-b bg-amber-50 p-3 text-center text-sm">{{ __('appearance.private_preview') }}</div>@endif
    <main @if(request()->has('theme_preview')) inert @endif id="main" class="mx-auto max-w-6xl px-5 py-16">@if(session('status'))<p class="mb-5 rounded-xl bg-emerald-50 p-4" role="status">{{ session('status') }}</p>@endif @if($errors->any())<div role="alert" class="mb-5 rounded-xl bg-rose-50 p-4 text-rose-800">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif @yield('content')</main>
    @include('themes.default.appearance.footer')
    @include('themes.default.content.popup')
    @include('themes.default.marketing.controls')
    @include('themes.default.marketing.tracking')
    <livewire:product-quick-view />
    @livewireScripts
</body>
</html>
