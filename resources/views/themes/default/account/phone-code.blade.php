@extends('themes.default.layout')
@section('title',__('integrations.phone_otp'))
@section('content')
<div class="mx-auto max-w-lg space-y-6 rounded-2xl border bg-white p-6"><h1 class="text-3xl font-semibold">{{ __('integrations.phone_otp') }}</h1><p>{{ __('integrations.phone_notice') }}</p>
<form method="POST" action="{{ route('phone-code.send') }}" class="space-y-4">@csrf
<x-account-input name="phone" type="tel" required autocomplete="tel"/><button class="btn-primary">{{ __('orders.send_code') }}</button></form>
<form method="POST" action="{{ route('phone-code.verify') }}" class="space-y-4 border-t pt-6">@csrf
<x-account-input name="phone" id="verify-phone" type="tel" required autocomplete="tel"/><x-account-input name="code" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code"/><button class="btn-primary">{{ __('orders.verify_login') }}</button></form></div>
@endsection
