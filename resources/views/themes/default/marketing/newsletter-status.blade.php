@extends('themes.default.layout')
@section('title',__('marketing.newsletter'))
@section('content')<div class="mx-auto max-w-xl space-y-6 rounded-2xl border bg-white p-8"><h1 class="text-3xl font-semibold">{{ $unsubscribed?__('marketing.newsletter_unsubscribed'):($confirmed?__('marketing.newsletter_confirmed'):__('marketing.newsletter_link_expired')) }}</h1><a class="btn-secondary" href="{{ route('home') }}">{{ __('store.home') }}</a></div>@endsection
