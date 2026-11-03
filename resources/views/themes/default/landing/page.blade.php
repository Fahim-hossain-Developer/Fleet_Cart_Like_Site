@php
 $theme=array_replace(app(\App\Domain\Settings\Theme\Theme::class)->current(),$page->theme_config??[]);
 $landingConfig=['mode'=>$page->form_config['mode']??'inline','locale'=>app()->getLocale(),'currency'=>config('checkout.base_currency'),'phoneOtp'=>!empty($page->form_config['phone_otp']),'csrf'=>csrf_token(),'routes'=>['quick'=>route('landing.quick',[$page->slug,0]),'children'=>route('locations.children'),'shipping'=>route('landing.shipping',$page->slug),'quote'=>route('landing.quote',$page->slug),'place'=>route('landing.place',$page->slug),'sendCode'=>route('landing.code.send',$page->slug),'verifyCode'=>route('landing.code.verify',$page->slug),'touched'=>route('landing.touched',$page->slug),'recovery'=>route('landing.recovery',$page->slug)],'messages'=>['failed'=>__('landing.request_failed'),'changed'=>__('landing.quote_changed'),'codeQueued'=>__('landing.code_queued'),'verified'=>__('landing.phone_verified'),'saved'=>__('marketing.recovery_saved')]];
@endphp
@extends('themes.default.landing.layout')
@section('content')
<div class="space-y-14">
@foreach($page->sections as $section)
@php($type=$section['type'])
@if($type==='order_form')@include('themes.default.landing.order-form')
@elseif(in_array($type,\App\Domain\Content\Actions\SectionCatalog::TYPES))
@php($homeBlock=new \App\Domain\Content\Models\HomeSection(['type'=>$type,'title'=>$section['title']??[],'body'=>$section['body']??[],'config'=>$section['config']??[],'status'=>'published','is_active'=>true,'sort'=>0]))
@php($homeBlock->id='landing-'.$page->id.'-'.$loop->index)
@include('themes.default.content.section',['section'=>$homeBlock,'headingLevel'=>2])
@else @include('themes.default.landing.section')
@endif
@endforeach
</div>
@if(($page->form_config['mode']??'inline')==='sticky')<div data-mobile-purchase-bar class="fixed bottom-0 inset-x-0 z-40 border-t bg-white p-3 md:hidden"><button type="button" @click="openOrder()" class="btn-primary w-full">{{ __('landing.order_now') }}</button></div><div class="h-20 md:hidden"></div>@endif
@endsection
