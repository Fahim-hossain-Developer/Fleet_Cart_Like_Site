@extends('themes.default.layout')
@section('title',__('marketing.newsletter_unsubscribe'))
@section('content')<form method="POST" action="{{ route('newsletter.unsubscribe',$token) }}" class="mx-auto max-w-xl space-y-6 rounded-2xl border bg-white p-8">@csrf
<h1 class="text-3xl font-semibold">{{ __('marketing.newsletter_unsubscribe') }}</h1><p>{{ __('marketing.newsletter_unsubscribe_help') }}</p><button class="btn-primary">{{ __('marketing.confirm_unsubscribe') }}</button></form>@endsection
