@extends('themes.default.layout')
@section('title', $product->seo[app()->getLocale()]['title'] ?? $product->seo['title'] ?? $product->name)
@section('description', $product->seo['description'] ?? strip_tags($product->short_desc ?? ''))
@section('canonical', $product->seo['canonical'] ?? route('product', $product->slug))
@section('content')<livewire:product-details :product-id="$product->id" /><div class="mt-8">@include('themes.default.marketing.list-buttons',['product'=>$product])@include('themes.default.marketing.watch-buttons',['product'=>$product])</div>@include('themes.default.marketing.feedback',['product'=>$product])@endsection
