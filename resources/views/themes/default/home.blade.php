@extends('themes.default.layout')
@section('content')
<div class="space-y-16">@forelse($sections as $section)@include('themes.default.content.section',['section'=>$section])@empty @include('themes.default.content.home-fallback') @endforelse</div>
@endsection
