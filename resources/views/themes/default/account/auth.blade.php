@extends('themes.default.layout')
@section('title',__('orders.'.$mode))
@section('content')
<div class="mx-auto grid max-w-5xl overflow-hidden rounded-2xl border bg-white lg:grid-cols-2">
<aside class="flex flex-col justify-center gap-6 bg-brand p-7 text-white md:p-12"><span class="text-sm font-semibold uppercase tracking-widest">{{ store_setting('name') }}</span><h2 class="text-3xl font-semibold">{{ __('marketing.auth_welcome') }}</h2><p>{{ __('marketing.auth_help') }}</p><a href="{{ route('shop') }}" class="inline-flex min-h-11 items-center underline">{{ __('orders.continue_guest') }}</a></aside>
<div class="space-y-6 p-6 md:p-10">
<h1 class="text-3xl font-semibold">{{ __('orders.'.$mode) }}</h1>
@if($mode==='otp')
<p>{{ __('orders.otp_notice') }}</p>
<form action="{{ route('otp.send') }}" method="POST" class="space-y-4">@csrf<x-account-input name="email" type="email" required autocomplete="email"/><button class="btn-primary">{{ __('orders.send_code') }}</button></form>
<form action="{{ route('otp.verify') }}" method="POST" class="space-y-4 border-t pt-6">@csrf<x-account-input name="email" id="verify-email" type="email" required autocomplete="email"/><x-account-input name="code" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code"/><button class="btn-primary">{{ __('orders.verify_login') }}</button></form>
@else
<form x-data="{strength:0}" @input="if($event.target.name==='password'){let v=$event.target.value;strength=[v.length>=12,/[a-z]/.test(v)&amp;&amp;/[A-Z]/.test(v),/[0-9]/.test(v),/[^a-zA-Z0-9]/.test(v)].filter(Boolean).length}" action="{{ $mode==='reset_password'? route('password.update') : url()->current() }}" method="POST" class="space-y-4">@csrf
@if($mode==='register')<x-account-input name="name" required autocomplete="name"/><x-account-input name="phone" type="tel" autocomplete="tel"/>@endif
@if($mode==='login')<x-account-input name="identifier" required autocomplete="username"/>@else<x-account-input name="email" type="email" :value="request('email')" required autocomplete="email"/>@endif
@if($mode==='reset_password')<input type="hidden" name="token" value="{{ request()->route('token') }}">@endif
@if($mode!=='forgot_password')<x-account-input name="password" type="password" required :autocomplete="$mode==='login'?'current-password':'new-password'" :minlength="$mode==='login'?null:12"/>@endif
@if(in_array($mode,['register','reset_password']))<x-account-input name="password_confirmation" type="password" required autocomplete="new-password"/><p class="text-sm text-stone-600">{{ __('orders.password_hint') }}</p><label class="block text-sm">{{ __('marketing.password_strength') }}<meter min="0" max="4" :value="strength" class="mt-2 block w-full"></meter></label>@endif
@if($mode==='login')<label class="flex min-h-11 items-center gap-3"><input type="checkbox" name="remember" value="1">{{ __('orders.remember') }}</label>@endif
<button class="btn-primary w-full">{{ __('orders.'.$mode) }}</button>
</form>
@endif
<a class="btn-secondary" href="{{ route('phone-code') }}">{{ __('integrations.phone_otp') }}</a>
@if(in_array($mode,['login','register']))<div class="flex flex-wrap gap-3">@foreach(['google','facebook'] as $provider)@if(app(\App\Domain\Customers\Actions\SocialLogin::class)->ready($provider))<a href="{{ route('social.start',$provider) }}" class="btn-secondary">{{ __('marketing.continue_provider',['provider'=>ucfirst($provider)]) }}</a>@endif @endforeach</div>@endif
<nav class="flex flex-wrap gap-4 text-sm text-brand">@foreach(['login','register','otp','forgot_password'] as $link)@if($mode!==$link)<a class="min-h-11 content-center underline" href="{{ route($link==='forgot_password'?'password.request':$link) }}">{{ __('orders.'.$link) }}</a>@endif @endforeach<a class="min-h-11 content-center underline" href="{{ route('shop') }}">{{ __('orders.continue_guest') }}</a></nav>
</div></div>
@endsection
