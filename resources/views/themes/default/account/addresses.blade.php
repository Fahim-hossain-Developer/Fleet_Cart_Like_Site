@extends('themes.default.layout')
@section('title',__('orders.addresses'))
@section('content')
@include('themes.default.account.nav')<h1 class="mb-8 text-3xl font-semibold">{{ __('orders.addresses') }}</h1><div class="grid gap-6 md:grid-cols-2">
@foreach($addresses as $address)<section class="rounded-xl border bg-white p-6"><form method="POST" action="{{ route('account.address.update',$address) }}" class="space-y-4">@csrf @include('themes.default.account.address-fields')<button class="btn-primary">{{ __('orders.save') }}</button></form><form method="POST" action="{{ route('account.address.delete',$address) }}" class="mt-3">@csrf @method('DELETE')<button class="btn-secondary">{{ __('orders.delete') }}</button></form></section>@endforeach
<section class="rounded-xl border bg-white p-6"><h2 class="mb-5 text-xl font-semibold">{{ __('orders.add_address') }}</h2><form method="POST" action="{{ route('account.address.store') }}" class="space-y-4">@csrf @include('themes.default.account.address-fields',['address'=>null])<button class="btn-primary">{{ __('orders.save') }}</button></form></section></div>
@endsection
