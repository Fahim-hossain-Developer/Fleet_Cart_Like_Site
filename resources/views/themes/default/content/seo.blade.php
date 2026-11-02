@php($seo=app(\App\Domain\Content\Actions\Seo::class)->data())
<meta name="description" content="{{ $seo['description'] }}">
<title>@yield('title', $seo['title'])</title>
<link rel="canonical" href="{{ $seo['canonical'] }}">
@if(!$seo['public'])<meta name="robots" content="noindex,nofollow">@endif
<meta property="og:title" content="{{ $seo['title'] }}"><meta property="og:description" content="{{ $seo['description'] }}"><meta property="og:type" content="{{ $seo['type'] }}"><meta property="og:url" content="{{ $seo['canonical'] }}"><meta property="og:locale" content="{{ app()->getLocale()==='bn'?'bn_BD':'en_US' }}">
<meta name="twitter:card" content="{{ $seo['image']?'summary_large_image':'summary' }}"><meta name="twitter:title" content="{{ $seo['title'] }}"><meta name="twitter:description" content="{{ $seo['description'] }}">
@if($seo['image'])<meta property="og:image" content="{{ $seo['image'] }}"><meta name="twitter:image" content="{{ $seo['image'] }}">@endif
@foreach($seo['hreflang'] as $language=>$url)<link rel="alternate" hreflang="{{ $language }}" href="{{ $url }}">@endforeach
@if($seo['public'])<link rel="alternate" hreflang="x-default" href="{{ url()->current() }}">@endif
@if(count($seo['schema']['@graph']))<script type="application/ld+json">{!! json_encode($seo['schema'],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR) !!}</script>@endif
