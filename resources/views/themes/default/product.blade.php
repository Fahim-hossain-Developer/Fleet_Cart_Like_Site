@extends('themes.default.layout')
@section('title', $product->seo['title'] ?? $product->name)
@section('description', $product->seo['description'] ?? strip_tags($product->short_desc ?? ''))
@section('canonical', $product->seo['canonical'] ?? route('product', $product->slug))
@section('content')<livewire:product-details :product-id="$product->id" />@endsection
