@extends('themes.default.layout')
@section('title',__('marketing.cart_recovery'))
@section('content')<div class="mx-auto max-w-xl space-y-5 rounded-xl border bg-white p-6"><h1 class="text-2xl font-semibold">{{ __('marketing.cart_recovery') }}</h1><p>{{ __('marketing.manual_draft_help') }}</p><a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="btn-primary">{{ __('marketing.open_whatsapp_draft') }}</a></div>@endsection
