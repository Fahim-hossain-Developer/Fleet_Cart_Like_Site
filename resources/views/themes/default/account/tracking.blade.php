@extends('themes.default.layout')
@section('title',__('orders.tracking'))
@section('content')
<div class="mx-auto max-w-2xl space-y-8"><h1 class="text-3xl font-semibold">{{ __('orders.tracking') }}</h1><form method="POST" action="{{ route('track-order') }}" class="space-y-4 rounded-xl border bg-white p-6">@csrf<x-account-input name="number" required/><x-account-input name="phone" type="tel" required/><button class="btn-primary">{{ __('orders.track') }}</button></form>
@if($order)<section class="space-y-4 rounded-xl border bg-white p-6"><h2 class="text-xl font-semibold">{{ $order->number }}</h2><p>{{ __('orders.status_'.$order->status) }} · {{ __('orders.fulfillment_'.$order->fulfillment_status) }}</p>@foreach($order->shipments as $s)<p>{{ $s->carrier }} · {{ $s->tracking_no }}</p>@endforeach<ol class="space-y-3">@foreach($order->events as $event)<li>{{ $event->created_at->format('d M Y H:i') }} · {{ $event->note }}</li>@endforeach</ol></section>@endif</div>
@endsection
