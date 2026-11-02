@extends('themes.default.layout')
@section('title',$order->number)
@section('content')
@include('themes.default.account.nav')
<h1 class="mb-6 text-3xl font-semibold">{{ $order->number }}</h1>
<div class="mb-8 flex flex-wrap gap-3">@if($order->total>$order->paid_amount && !in_array($order->status,['cancelled','failed','refunded','returned']))<a class="btn-primary" href="{{ route('payments.order',$order->number) }}">{{ __('payments.pay_order') }}</a>@endif@foreach(['invoice','packing','label'] as $type)<a class="btn-secondary" href="{{ URL::temporarySignedRoute('account.document',now()->addMinutes(15),['order'=>$order->id,'type'=>$type]) }}">{{ __('orders.'.$type) }}</a>@endforeach
<form method="POST" action="{{ route('account.order.action',$order->number) }}">@csrf<button name="action" value="reorder" class="btn-primary">{{ __('orders.reorder') }}</button></form>
@if($order->status==='pending')<form method="POST" action="{{ route('account.order.action',$order->number) }}">@csrf<button name="action" value="cancel" class="btn-secondary">{{ __('orders.cancel') }}</button></form>@endif</div>
@include('themes.default.account.order-summary')
@if(in_array($order->status,['delivered','completed']))<form method="POST" action="{{ route('account.order.action',$order->number) }}" class="mt-8 max-w-lg space-y-4 rounded-xl border bg-white p-6">@csrf<input type="hidden" name="action" value="return"><h2 class="text-xl font-semibold">{{ __('orders.request_return') }}</h2>@foreach($order->items as $item)<label class="block">{{ $item->name }}<input class="min-h-11 w-full rounded-lg border p-2" name="items[{{ $item->id }}]" type="number" min="0" max="{{ $item->qty }}" value="0"></label>@endforeach<x-account-input name="reason" required maxlength="2000"/><button class="btn-primary">{{ __('orders.request_return') }}</button></form>@endif
@foreach($order->returns as $return)<p class="mt-5 rounded-xl border p-4">{{ __('orders.request_return') }}: {{ __('orders.return_'.$return->status) }} · {{ $return->reason }} @if($return->staff_note)<br>{{ $return->staff_note }}@endif</p>@endforeach
@endsection
