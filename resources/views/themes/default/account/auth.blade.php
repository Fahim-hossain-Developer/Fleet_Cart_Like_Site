@extends('themes.default.layout')
@section('title',__('orders.'.$mode))
@section('content')
<div class="mx-auto max-w-lg space-y-6 rounded-2xl border border-stone-200 bg-white p-6 md:p-10">
<h1 class="text-3xl font-semibold">{{ __('orders.'.$mode) }}</h1>
@if($mode==='otp')
<p>{{ __('orders.otp_notice') }}</p>
<form action="{{ route('otp.send') }}" method="POST" class="space-y-4">@csrf<x-account-input name="email" type="email" required autocomplete="email"/><button class="btn-primary">{{ __('orders.send_code') }}</button></form>
<form action="{{ route('otp.verify') }}" method="POST" class="space-y-4 border-t pt-6">@csrf<x-account-input name="email" id="verify-email" type="email" required autocomplete="email"/><x-account-input name="code" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code"/><button class="btn-primary">{{ __('orders.verify_login') }}</button></form>
@else
<form action="{{ $mode==='reset_password'? route('password.update') : url()->current() }}" method="POST" class="space-y-4">@csrf
@if($mode==='register')<x-account-input name="name" required autocomplete="name"/><x-account-input name="phone" type="tel" autocomplete="tel"/>@endif
@if($mode==='login')<x-account-input name="identifier" required autocomplete="username"/>@else<x-account-input name="email" type="email" :value="request('email')" required autocomplete="email"/>@endif
@if($mode==='reset_password')<input type="hidden" name="token" value="{{ request()->route('token') }}">@endif
@if($mode!=='forgot_password')<x-account-input name="password" type="password" required :autocomplete="$mode==='login'?'current-password':'new-password'" :minlength="$mode==='login'?null:12"/>@endif
@if(in_array($mode,['register','reset_password']))<x-account-input name="password_confirmation" type="password" required autocomplete="new-password"/><p class="text-sm text-stone-600">{{ __('orders.password_hint') }}</p>@endif
@if($mode==='login')<label class="flex min-h-11 items-center gap-3"><input type="checkbox" name="remember" value="1">{{ __('orders.remember') }}</label>@endif
<button class="btn-primary w-full">{{ __('orders.'.$mode) }}</button>
</form>
@endif
<nav class="flex flex-wrap gap-4 text-sm text-brand">@foreach(['login','register','otp','forgot_password'] as $link)@if($mode!==$link)<a class="min-h-11 content-center underline" href="{{ route($link==='forgot_password'?'password.request':$link) }}">{{ __('orders.'.$link) }}</a>@endif @endforeach<a class="min-h-11 content-center underline" href="{{ route('shop') }}">{{ __('orders.continue_guest') }}</a></nav>
</div>
@endsection
