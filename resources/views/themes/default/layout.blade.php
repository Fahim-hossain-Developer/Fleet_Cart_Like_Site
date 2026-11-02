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
    @if(store_setting('catalog_top_bar',true))<div class="flex flex-wrap items-center justify-center gap-4 bg-brand px-4 py-2 text-sm text-white"><span>{{ __('store.announcement') }}</span><form method="POST" action="{{ route('currency.update') }}">@csrf<label class="sr-only" for="display-currency">{{ __('checkout.currency') }}</label><select id="display-currency" name="currency" onchange="this.form.requestSubmit()" class="min-h-11 rounded-lg border border-white/30 bg-brand px-2">@foreach(\App\Domain\Settings\Models\Currency::where('is_active',true)->get() as $currency)<option value="{{ $currency->code }}" @selected(app(\App\Domain\Settings\Actions\CurrencyDisplay::class)->current()?->code===$currency->code)>{{ $currency->code }}</option>@endforeach</select><noscript><button>{{ __('checkout.apply') }}</button></noscript></form>                <form action="{{ route('locale.update') }}" method="POST">@csrf
                    <label for="locale" class="sr-only">{{ __('store.language') }}</label>
                    <select id="locale" name="locale" class="min-h-11 rounded-lg border border-white/30 bg-brand px-2" onchange="this.form.requestSubmit()">
                        <option value="en" @selected(app()->getLocale() === 'en')>English</option><option value="bn" @selected(app()->getLocale() === 'bn')>বাংলা</option>
                    </select><button class="min-h-11 px-2" type="submit">{{ __('store.apply') }}</button>
                </form><a href="{{ route('shop') }}">{{ __('catalog.shop') }}</a></div>@endif
    <header class="border-b border-stone-200 bg-white">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-5 py-5">
            <a href="{{ route('home') }}" class="text-xl font-bold tracking-tight md:text-2xl">{{ store_setting('name') }}<span class="text-brand">.</span></a>
            <nav aria-label="{{ __('store.navigation') }}" class="hidden gap-6 md:flex">@if($headerItems=app(\App\Domain\Content\Actions\Navigation::class)->tree('header'))@include('themes.default.content.menu',['items'=>$headerItems])@else<a href="{{ route('home') }}" aria-current="page" class="min-h-11 content-center font-medium">{{ __('store.home') }}</a><a href="{{ route('shop') }}" class="min-h-11 content-center">{{ __('catalog.shop') }}</a>@endif</nav>
            <div class="flex flex-wrap items-center gap-2"><a class="min-h-11 content-center px-2" href="{{ auth('web')->check()?route('account'):route('login') }}">{{ __('orders.account') }}</a><a class="min-h-11 content-center px-2" href="{{ route('track-order') }}">{{ __('orders.tracking') }}</a><a href="{{ route('wishlist') }}" class="min-h-11 content-center px-2">{{ __('marketing.wishlist') }}</a><a href="{{ route('compare') }}" class="min-h-11 content-center px-2">{{ __('marketing.compare') }}</a>@auth('web')<a class="min-h-11 content-center px-2" href="{{ route('notifications') }}">{{ __('marketing.notifications') }} ({{ \App\Domain\Customers\Models\StoreNotification::where('notifiable_type',\App\Models\User::class)->where('notifiable_id',auth('web')->id())->where('data->in_app',true)->whereNull('read_at')->count() }})</a>@endauth<livewire:mini-cart />
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
                @if($mobileItems=app(\App\Domain\Content\Actions\Navigation::class)->tree('mobile'))@include('themes.default.content.menu',['items'=>$mobileItems])@else<a href="{{ route('home') }}" class="block min-h-11 py-3">{{ __('store.home') }}</a><a href="{{ route('shop') }}" class="block min-h-11 py-3">{{ __('catalog.shop') }}</a>@endif
            </nav>
        </div>
    </header>
    <main id="main" class="mx-auto max-w-6xl px-5 py-16">@if(session('status'))<p class="mb-5 rounded-xl bg-emerald-50 p-4" role="status">{{ session('status') }}</p>@endif @if($errors->any())<div role="alert" class="mb-5 rounded-xl bg-rose-50 p-4 text-rose-800">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif @yield('content')</main>
    <footer class="border-t border-stone-200 bg-white"><div class="mx-auto flex max-w-6xl flex-wrap justify-between gap-4 px-5 py-8"><span class="font-semibold">{{ store_setting('name') }}</span><p class="text-sm text-stone-600">{{ __('store.footer') }}</p><nav class="flex flex-wrap gap-6" aria-label="{{ __('content.footer_navigation') }}">@include('themes.default.content.menu',['items'=>app(\App\Domain\Content\Actions\Navigation::class)->tree('footer')])</nav></div></footer>
    @livewireScripts
</body>
</html>
