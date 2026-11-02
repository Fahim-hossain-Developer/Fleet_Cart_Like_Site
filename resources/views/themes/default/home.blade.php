@extends('themes.default.layout')
@section('content')
<section class="grid items-center gap-10 py-10 md:grid-cols-2">
    <div><p class="mb-5 text-sm font-semibold uppercase tracking-widest text-brand">{{ __('store.eyebrow') }}</p>
        <h1 class="max-w-xl text-4xl font-semibold leading-tight tracking-tight md:text-6xl">{{ __('store.heading') }}</h1>
        <p class="mt-6 max-w-lg text-lg leading-8 text-stone-600">{{ __('store.description') }}</p>
        <p class="mt-8 inline-flex rounded-full bg-white px-5 py-3 text-sm ring-1 ring-stone-200">{{ __('store.status') }}</p>
    </div>
    <div aria-hidden="true" class="relative flex aspect-square items-center justify-center overflow-hidden rounded-3xl bg-emerald-100">
        <div class="absolute h-64 w-64 rounded-full bg-emerald-200"></div><div class="relative rotate-6 rounded-3xl bg-white p-12 text-7xl font-bold text-brand shadow-xl">{{ mb_substr((string) store_setting('name'), 0, 1) }}.</div>
    </div>
</section>
@endsection
