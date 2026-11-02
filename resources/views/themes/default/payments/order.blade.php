@extends('themes.default.layout')
@section('title',__('payments.pay_order'))
@section('content')
<div class="mx-auto max-w-2xl space-y-6"><h1 class="text-3xl font-semibold">{{ __('payments.pay_order') }}</h1><p>{{ $order->number }}</p><dl class="grid grid-cols-2 gap-3 rounded-xl border bg-white p-6"><dt>{{ __('orders.total') }}</dt><dd>{{ $order->currency }} {{ number_format($order->total/100,2) }}</dd><dt>{{ __('orders.paid_amount') }}</dt><dd>{{ $order->currency }} {{ number_format($order->paid_amount/100,2) }}</dd><dt>{{ $advance>0?__('payments.advance_due'):__('payments.balance_due') }}</dt><dd class="font-semibold">{{ $order->currency }} {{ number_format($due/100,2) }}</dd></dl>
@if($order->payment_environment==='sandbox')<p class="rounded-xl bg-amber-50 p-4">{{ __('payments.test_notice') }}</p>@endif
@if($active)<p>{{ __('payments.pending_operation') }}</p><a class="btn-primary" href="{{ route('payments.status',$active->uuid) }}">{{ __('payments.view_payment') }}</a>
@elseif($due>0 && in_array($order->status,['pending','confirmed','processing','packed','on-hold']) && !$order->shipments()->exists())
<form method="POST" action="{{ route('payments.start',$order->number) }}" class="space-y-5 rounded-xl border bg-white p-6">@csrf<input type="hidden" name="key" value="{{ $key }}"><label class="block">{{ __('payments.provider') }}<select name="driver" aria-label="{{ __('payments.provider') }}" required class="mt-2 min-h-11 w-full rounded-lg border bg-white p-3">@foreach($gateways as $driver=>$label)<option value="{{ $driver }}">{{ $label }}</option>@endforeach</select></label><label class="block">{{ __('payments.payment_amount') }}<select name="purpose" aria-label="{{ __('payments.payment_amount') }}" class="mt-2 min-h-11 w-full rounded-lg border bg-white p-3"><option value="due">{{ $advance>0?__('payments.pay_advance'):__('payments.pay_balance') }}</option>@if($advance>0)<option value="full">{{ __('payments.pay_full') }}</option>@endif</select></label>
@if(!$order->guest_email)<x-account-input name="email" type="email" :label="__('payments.email_optional')" autocomplete="email"/><p class="text-sm text-stone-600">{{ __('payments.email_help') }}</p>@endif
<button class="btn-primary w-full" @disabled(!$gateways)>{{ __('payments.continue_provider') }}</button>@if(!$gateways)<p>{{ __('payments.no_provider') }}</p>@endif</form>
@else<p>{{ __('payments.no_payment_due') }}</p>@endif
<a class="btn-secondary" href="{{ route('checkout.success',$order->number) }}">{{ __('payments.back_order') }}</a></div>
@endsection
