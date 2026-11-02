@extends('themes.default.layout')
@section('title',__('content.faq'))
@section('content')
<div class="mx-auto max-w-3xl"><h1 class="mb-8 text-4xl font-semibold">{{ __('content.faq') }}</h1><div class="space-y-4">@forelse($entries as $entry)<details class="rounded-xl border bg-white p-6"><summary class="min-h-11 cursor-pointer font-semibold">{{ $entry->title }}</summary><div class="content-prose mt-4">{!! \App\Domain\Content\Actions\SafeContent::html($entry->body) !!}</div></details>@empty<p>{{ __('content.no_faq') }}</p>@endforelse</div></div>
@endsection
