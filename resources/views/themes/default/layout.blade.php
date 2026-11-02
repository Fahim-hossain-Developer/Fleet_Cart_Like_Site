<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="@yield('description', __('store.description'))">
    <title>@yield('title', store_setting('name').' · '.__('store.tagline'))</title>
    <link rel="canonical" href="@yield('canonical', url()->current())">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-stone-50 text-stone-900 antialiased" x-data="{ menuOpen: false }" @keydown.escape.window="menuOpen = false">
    <a href="#main" class="sr-only focus:not-sr-only focus:block focus:p-4">{{ __('store.skip') }}</a>
    @if(store_setting('catalog_top_bar',true))<div class="flex flex-wrap items-center justify-center gap-4 bg-brand px-4 py-2 text-sm text-white"><span>{{ __('store.announcement') }}</span><span>{{ store_setting('currency') }}</span>                <form action="{{ route('locale.update') }}" method="POST">@csrf
                    <label for="locale" class="sr-only">{{ __('store.language') }}</label>
                    <select id="locale" name="locale" class="min-h-11 rounded-lg border border-white/30 bg-brand px-2" onchange="this.form.requestSubmit()">
                        <option value="en" @selected(app()->getLocale() === 'en')>English</option><option value="bn" @selected(app()->getLocale() === 'bn')>বাংলা</option>
                    </select><button class="min-h-11 px-2" type="submit">{{ __('store.apply') }}</button>
                </form><a href="{{ route('shop') }}">{{ __('catalog.shop') }}</a></div>@endif
    <header class="border-b border-stone-200 bg-white">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-5 py-5">
            <a href="{{ route('home') }}" class="text-2xl font-bold tracking-tight">{{ store_setting('name') }}<span class="text-brand">.</span></a>
            <nav aria-label="{{ __('store.navigation') }}" class="hidden gap-6 md:flex"><a href="{{ route('home') }}" aria-current="page" class="min-h-11 content-center font-medium">{{ __('store.home') }}</a><a href="{{ route('shop') }}" class="min-h-11 content-center">{{ __('catalog.shop') }}</a></nav>
            <div class="flex items-center gap-3">
@unless(store_setting('catalog_top_bar',true))                <form action="{{ route('locale.update') }}" method="POST">@csrf
                    <label for="locale" class="sr-only">{{ __('store.language') }}</label>
                    <select id="locale" name="locale" class="min-h-11 rounded-lg border border-stone-300 px-2" onchange="this.form.requestSubmit()">
                        <option value="en" @selected(app()->getLocale() === 'en')>English</option><option value="bn" @selected(app()->getLocale() === 'bn')>বাংলা</option>
                    </select><button class="min-h-11 px-2" type="submit">{{ __('store.apply') }}</button>
                </form>@endunless
                <button type="button" class="min-h-11 min-w-11 rounded-lg border border-stone-300 md:hidden" @click="menuOpen = !menuOpen; if(menuOpen) $nextTick(() => $refs.closeMenu.focus())" :aria-expanded="menuOpen" aria-controls="mobile-menu" aria-label="{{ __('store.open_menu') }}">☰</button>
            </div>
        </div>
        <div class="mx-auto max-w-6xl px-5 pb-5"><livewire:header-search /></div>
        <div x-cloak x-show="menuOpen" class="fixed inset-0 z-50 bg-black/30 md:hidden" @click.self="menuOpen = false">
            <nav id="mobile-menu" x-trap.inert.noscroll="menuOpen" aria-label="{{ __('store.navigation') }}" class="h-full w-72 bg-white p-6">
                <button x-ref="closeMenu" class="mb-8 min-h-11 rounded-lg border px-4" @click="menuOpen = false">{{ __('store.close') }}</button>
                <a href="{{ route('home') }}" class="block min-h-11 py-3">{{ __('store.home') }}</a><a href="{{ route('shop') }}" class="block min-h-11 py-3">{{ __('catalog.shop') }}</a>
            </nav>
        </div>
    </header>
    <main id="main" class="mx-auto max-w-6xl px-5 py-16">@yield('content')</main>
    <footer class="border-t border-stone-200 bg-white"><div class="mx-auto flex max-w-6xl flex-wrap justify-between gap-4 px-5 py-8"><span class="font-semibold">{{ store_setting('name') }}</span><p class="text-sm text-stone-600">{{ __('store.footer') }}</p></div></footer>
    @livewireScripts
</body>
</html>
