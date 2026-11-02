@extends('themes.default.layout')
@section('title',__('marketing.'.$kind))
@section('content')
<h1 class="mb-8 text-4xl font-semibold">{{ __('marketing.'.$kind) }}</h1>
@if($products->isEmpty())<p class="rounded-xl border bg-white p-8">{{ __('marketing.list_empty') }}</p><a href="{{ route('shop') }}" class="btn-primary mt-6">{{ __('catalog.browse') }}</a>
@elseif($kind==='compare')
<div class="overflow-x-auto rounded-2xl border bg-white"><table class="w-full min-w-[640px] text-start"><thead><tr><th class="p-5 text-start">{{ __('marketing.feature') }}</th>@foreach($products as $product)<th class="p-5 text-start"><a class="text-brand" href="{{ route('product',$product->slug) }}">{{ $product->name }}</a></th>@endforeach</tr></thead><tbody>
<tr class="border-t"><th class="p-5 text-start">{{ __('catalog.price') }}</th>@foreach($products as $product)<td class="p-5">{{ catalog_money($product->displayPrice()) }}</td>@endforeach</tr>
<tr class="border-t"><th class="p-5 text-start">{{ __('catalog.brand') }}</th>@foreach($products as $product)<td class="p-5">{{ $product->brand?->name??'—' }}</td>@endforeach</tr>
<tr class="border-t"><th class="p-5 text-start">{{ __('catalog.stock') }}</th>@foreach($products as $product)<td class="p-5">{{ __($product->available()?'catalog.in_stock':'catalog.out_of_stock') }}</td>@endforeach</tr>
<tr class="border-t"><th class="p-5 text-start">{{ __('content.details') }}</th>@foreach($products as $product)<td class="p-5">{{ strip_tags($product->short_desc??'') }}@foreach($product->attributeValues as $value)<p class="mt-2">{{ $value->attribute?->name }}: {{ $value->name }}</p>@endforeach</td>@endforeach</tr>
<tr class="border-t"><th class="p-5 text-start">{{ __('marketing.actions') }}</th>@foreach($products as $product)<td class="p-5">@include('themes.default.marketing.list-remove',['product'=>$product,'kind'=>$kind])</td>@endforeach</tr></tbody></table></div>
@else<div class="grid grid-cols-2 gap-5 md:grid-cols-4">@foreach($products as $product)<div class="space-y-3">@include('themes.default.partials.card',['item'=>$product])@include('themes.default.marketing.list-remove',['product'=>$product,'kind'=>$kind])</div>@endforeach</div>@endif
@endsection
