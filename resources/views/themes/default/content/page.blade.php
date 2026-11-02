@extends('themes.default.layout')
@section('title',$entry->seo[app()->getLocale()]['title']??$entry->title)
@section('description',$entry->seo[app()->getLocale()]['description']??strip_tags($entry->excerpt??''))
@section('canonical',$entry->seo['canonical']??url()->current())
@section('content')
<article class="{{ ($entry->template??'standard')==='wide'?'':'mx-auto max-w-3xl' }} space-y-6">
@if($preview)<p class="rounded-xl bg-amber-50 p-4">{{ __('content.preview_notice') }}</p>@endif
<h1 class="text-4xl font-semibold tracking-tight md:text-5xl">{{ $entry->title }}</h1>
@if($entry instanceof \App\Domain\Content\Models\Post)<p class="text-sm text-stone-600">{{ $entry->published_at?->locale(app()->getLocale())->translatedFormat('j F Y') }} @if($entry->category)· {{ $entry->category->title }}@endif</p>@endif
@if($entry->excerpt)<p class="text-lg leading-8 text-stone-600">{{ strip_tags($entry->excerpt) }}</p>@endif
<div class="content-prose">{!! \App\Domain\Content\Actions\SafeContent::html($entry->body) !!}</div>
@if($entry instanceof \App\Domain\Content\Models\Post)<div class="flex flex-wrap gap-3">@foreach($entry->tags??[] as $tag)<a class="rounded-full border px-4 py-2" href="{{ route('blog',['tag'=>$tag]) }}">{{ $tag }}</a>@endforeach</div>@endif
</article>
@endsection
