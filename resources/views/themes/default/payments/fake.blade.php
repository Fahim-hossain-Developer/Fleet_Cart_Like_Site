@extends('themes.default.layout')
@section('title',__('payments.local_test_gateway'))
@section('content')
<div class="mx-auto max-w-xl space-y-6 rounded-2xl border bg-white p-6 md:p-10"><h1 class="text-3xl font-semibold">{{ __('payments.local_test_gateway') }}</h1><p class="rounded-xl bg-amber-50 p-4">{{ __('payments.test_notice') }}</p><p>{{ $order->number }} · {{ $attempt->currency }} {{ number_format($attempt->amount/100,2) }}</p><form method="POST" action="{{ request()->fullUrl() }}" class="flex flex-wrap gap-3">@csrf
@foreach(['paid','failed','cancelled'] as $outcome)<button class="{{ $outcome==='paid'?'btn-primary':'btn-secondary' }}" name="outcome" value="{{ $outcome }}">{{ __('payments.simulate_'.$outcome) }}</button>@endforeach</form></div>
@endsection
