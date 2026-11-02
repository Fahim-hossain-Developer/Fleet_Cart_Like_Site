@extends('themes.default.layout')
@section('title',__('integrations.admin_otp'))
@section('content')
<form method="POST" action="{{ route('admin.otp.verify') }}" class="mx-auto max-w-lg space-y-6 rounded-2xl border bg-white p-6">@csrf
<h1 class="text-3xl font-semibold">{{ __('integrations.admin_otp') }}</h1><p>{{ __('integrations.admin_notice') }}</p><x-account-input name="code" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code"/><button class="btn-primary">{{ __('orders.verify_login') }}</button><a class="btn-secondary" href="/admin/login">{{ __('orders.login') }}</a></form>
@endsection
