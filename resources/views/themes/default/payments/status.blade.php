@extends('themes.default.layout')
@section('title',__('payments.payment_status'))
@section('content')
<div class="mx-auto max-w-xl space-y-6 rounded-2xl border bg-white p-6 md:p-10"><h1 class="text-3xl font-semibold">{{ __('payments.payment_status') }}</h1><p>{{ $order->number }}</p><p class="font-semibold">{{ __('payments.status_'.$attempt->status) }}</p><p>{{ __('integrations.provider_'.$attempt->driver) }} · {{ $attempt->currency }} {{ number_format($attempt->amount/100,2) }}</p><p class="text-sm">{{ __('payments.reference') }}: {{ $attempt->merchant_reference }}</p>
@if($attempt->mode==='sandbox')<p class="rounded-xl bg-amber-50 p-4">{{ __('payments.test_notice') }}</p>@endif
@if($attempt->status==='succeeded')<p>{{ __('payments.verified_message') }}</p>
@elseif($attempt->status==='review_required')<p>{{ __('payments.payment_review') }}</p>
@elseif($attempt->status==='unknown')<p>{{ __('payments.unknown_message') }}</p>@endif
@if($attempt->status==='initiated'&&$attempt->redirect_url&&app(\App\Domain\Payments\Actions\GatewayManager::class)->redirectAllowed($attempt->driver,$attempt->redirect_url))<a class="btn-primary w-full" href="{{ $attempt->redirect_url }}">{{ __('payments.open_provider') }}</a>@endif
@if(!in_array($attempt->status,['succeeded','review_required','refunded_unapplied']))<form method="POST" action="{{ route('payments.check',$attempt->uuid) }}">@csrf<button class="btn-secondary w-full">{{ __('payments.check_complete') }}</button></form>@endif
<a class="btn-secondary w-full" href="{{ route('checkout.success',$order->number) }}">{{ __('payments.back_order') }}</a></div>
@endsection
