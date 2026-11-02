@extends('themes.default.layout')
@section('content')
<livewire:catalog-browser :category-slug="$categorySlug" :brand-slug="$brandSlug" />
@endsection
