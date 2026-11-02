@extends('themes.default.layout')
@section('title',__('orders.downloads'))
@section('content')
@include('themes.default.account.nav')<h1 class="mb-6 text-3xl font-semibold">{{ __('orders.downloads') }}</h1><div class="space-y-4">@forelse($downloads as $download)<div class="flex flex-wrap items-center justify-between gap-4 rounded-xl border bg-white p-5"><span>{{ $download->label }} · {{ $download->used }}/{{ $download->limit??'∞' }} @if($download->expires_at) · {{ $download->expires_at->format('d M Y') }}@endif</span><a class="btn-primary" href="{{ URL::temporarySignedRoute('account.download',now()->addMinutes(10),$download->id) }}">{{ __('orders.download') }}</a></div>@empty<p>{{ __('orders.no_downloads') }}</p>@endforelse</div>
@endsection
