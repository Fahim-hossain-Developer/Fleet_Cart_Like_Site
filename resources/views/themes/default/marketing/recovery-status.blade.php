@extends('themes.default.layout')
@section('title', __('marketing.cart_recovery'))
@section('content')
<div class="mx-auto max-w-xl rounded-2xl border bg-white p-6">
<h1 class="text-2xl font-semibold">{{ __('marketing.cart_recovery') }}</h1>
@if($status === 410)<p class="mt-5">{{ __('marketing.recovery_expired') }}</p>
@elseif($state === 'confirmed')<p class="mt-5">{{ __('marketing.recovery_confirmed') }}</p>
@elseif($state === 'stopped')<p class="mt-5">{{ __('marketing.recovery_stopped') }}</p>
@else
<p class="mt-5">{{ __('marketing.recovery_'.$state.'_notice') }}</p>
<form method="POST" action="{{ $state === 'restore' ? route('recovery.restore', $token) : route('recovery.stop', $token) }}" class="mt-5">@csrf<button class="min-h-11 rounded-lg bg-brand px-5 py-3 text-white">{{ __('marketing.recovery_'.$state) }}</button></form>
@endif
</div>
@endsection
