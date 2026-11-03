@php
 $config=$section->config??[];$type=$section->type;
 $data=app(\App\Domain\Content\Actions\SectionCatalog::class)->data($type,$config);
 $text=fn($value)=>is_array($value)?($value[app()->getLocale()]??$value['en']??''):($value??'');
 $image=fn($path)=>\App\Domain\Content\Actions\SafeContent::imageUrl($path);
 $video=fn($url)=>\App\Domain\Content\Actions\SafeContent::video($url);
@endphp
<section data-section-style="{{ $config['style']??app(\App\Domain\Settings\Theme\Theme::class)->current()['section_style'] }}" class="theme-home-section space-y-6" aria-labelledby="section-{{ $section->id }}"><div><h{{ $loop->first?1:2 }} id="section-{{ $section->id }}" class="text-3xl font-semibold tracking-tight">{{ $section->title }}</h{{ $loop->first?1:2 }}>@if($section->excerpt)<p class="mt-3 max-w-3xl text-lg text-stone-600">{{ strip_tags($section->excerpt) }}</p>@endif</div>
@if($type==='hero_slider')
<div x-data="{index:0,count:{{ $data['slides']->count() }}}" class="relative overflow-hidden rounded-3xl bg-emerald-50" @keydown.arrow-right.prevent="index=(index+1)%count" @keydown.arrow-left.prevent="index=(index+count-1)%count">
@foreach($data['slides'] as $slide)<article x-show="index==={{ $loop->index }}" @if(!$loop->first)style="display:none"@endif class="grid items-center gap-8 p-6 md:grid-cols-2 md:p-12"><div class="space-y-6"><h3 class="text-3xl font-semibold md:text-5xl">{{ $slide->title }}</h3><div class="content-prose">{!! \App\Domain\Content\Actions\SafeContent::html($slide->body) !!}</div>@if($slide->url)<a class="btn-primary" href="{{ $slide->url }}">{{ $text($slide->button_label)?:__('content.explore') }}</a>@endif</div>@if($slide->image)<picture>@if($slide->mobile_image)<source media="(max-width:640px)" srcset="{{ $image($slide->mobile_image) }}">@endif<img src="{{ $image($slide->image) }}" alt="{{ $slide->title }}" width="1200" height="900" loading="{{ $loop->first?'eager':'lazy' }}" @if($loop->first)fetchpriority="high"@endif class="aspect-[4/3] w-full rounded-2xl object-cover"></picture>@endif</article>@endforeach
@if($data['slides']->count()>1)<div class="flex justify-center gap-4 p-4"><button class="btn-secondary" @click="index=(index+count-1)%count">{{ __('content.previous') }}</button><span class="content-center" x-text="(index+1)+' / '+count"></span><button class="btn-secondary" @click="index=(index+1)%count">{{ __('content.next') }}</button></div>@endif</div>
@elseif($type==='category_grid')
<div class="grid grid-cols-2 gap-4 md:grid-cols-4">@foreach($data['categories'] as $category)<a class="min-h-24 content-center rounded-2xl border bg-white p-6 text-lg font-semibold" href="{{ route('category',$category->slug) }}">{{ $category->name }}</a>@endforeach</div>
@elseif(in_array($type,['product_carousel','product_tabs']))
@if($type==='product_tabs')
<div x-data="{tab:'new'}" class="space-y-6"><div role="tablist" aria-label="{{ $section->title }}" class="flex flex-wrap gap-3">@foreach(['new','best_seller','sale'] as $source)<button id="tab-{{ $section->id }}-{{ $source }}" class="btn-secondary" role="tab" :aria-selected="tab==='{{ $source }}'" aria-controls="panel-{{ $section->id }}-{{ $source }}" @click="tab='{{ $source }}'">{{ __('content.source_'.$source) }}</button>@endforeach</div>
@foreach(['new','best_seller','sale'] as $source)<div id="panel-{{ $section->id }}-{{ $source }}" role="tabpanel" aria-labelledby="tab-{{ $section->id }}-{{ $source }}" x-show="tab==='{{ $source }}'" @if(!$loop->first)style="display:none"@endif class="grid grid-cols-2 gap-4 md:grid-cols-4">@foreach(app(\App\Domain\Content\Actions\SectionCatalog::class)->data('product_carousel',array_replace($config,['source'=>$source]))['products'] as $product)@include('themes.default.partials.card',['item'=>$product])@endforeach</div>@endforeach</div>
@else<div class="grid grid-cols-2 gap-4 md:grid-cols-4">@foreach($data['products'] as $product)@include('themes.default.partials.card',['item'=>$product])@endforeach</div>@endif
@elseif($type==='banner_row')
<div class="grid gap-5 md:grid-cols-2">@foreach($data['banners'] as $banner)@include('themes.default.content.banner',['banner'=>$banner])@endforeach</div>
@elseif(in_array($type,['brand_logos','shop_by_brand']))
<div class="flex flex-wrap gap-4">@foreach($data['brands'] as $brand)<a class="btn-secondary" href="{{ route('brand',$brand->slug) }}">@if($brand->image)<img src="{{ asset('storage/'.$brand->image) }}" alt="" width="120" height="60" loading="lazy" class="h-12 w-24 object-contain">@endif<span>{{ $brand->name }}</span></a>@endforeach</div>
@elseif($type==='blog_posts')
<div class="grid gap-6 md:grid-cols-3">@foreach($data['posts'] as $post)<article class="rounded-2xl border bg-white p-6"><h3 class="text-xl font-semibold"><a href="{{ route('blog.post',$post->slug) }}">{{ $post->title }}</a></h3><p class="mt-3">{{ strip_tags($post->excerpt??'') }}</p><a href="{{ route('blog.post',$post->slug) }}" class="mt-4 inline-flex min-h-11 items-center underline">{{ __('content.read_more') }}</a></article>@endforeach</div>
@elseif($type==='rich_text')
<div class="content-prose rounded-2xl border bg-white p-6 md:p-10">{!! \App\Domain\Content\Actions\SafeContent::html($section->body) !!}</div>
@elseif($type==='newsletter')
@if(\Illuminate\Support\Facades\Route::has('newsletter.subscribe'))@include('themes.default.marketing.newsletter')@endif
@elseif($type==='video')
@if($embed=$video($config['video_url']??null))<iframe src="{{ $embed }}" title="{{ $section->title }}" loading="lazy" sandbox="allow-scripts allow-same-origin allow-presentation" allowfullscreen class="aspect-video w-full rounded-2xl border-0" referrerpolicy="strict-origin-when-cross-origin"></iframe>@endif
@elseif($type==='countdown')
<div class="rounded-2xl bg-brand p-8 text-white"><div class="content-prose">{!! \App\Domain\Content\Actions\SafeContent::html($section->body) !!}</div>@if(!empty($config['ends_at']))<p class="mt-5 text-2xl font-semibold" x-data="{end:Date.parse(@js($config['ends_at'])),now:Date.now()}" x-init="setInterval(()=>now=Date.now(),1000)" x-text="end>now?Math.floor((end-now)/86400000)+'d '+new Date(end-now).toISOString().slice(11,19):@js(__('content.offer_ended'))">{{ \Illuminate\Support\Carbon::parse($config['ends_at'])->format('d M Y H:i') }}</p>@endif</div>
@elseif($type==='comparison')
<div class="overflow-x-auto rounded-2xl border bg-white"><table class="w-full text-start"><thead><tr><th class="p-4 text-start">{{ __('content.feature') }}</th><th class="p-4 text-start">{{ __('content.details') }}</th></tr></thead><tbody>@foreach($config['items']??[] as $item)<tr class="border-t"><th class="p-4 text-start font-medium">{{ $text($item['title']??'') }}</th><td class="p-4">{{ $text($item['body']??'') }}</td></tr>@endforeach</tbody></table></div>
@elseif($type==='image_hotspots')
<div class="relative overflow-hidden rounded-2xl">@if(!empty($config['image']))<img class="w-full rounded-2xl" src="{{ $image($config['image']) }}" alt="{{ $section->title }}" width="1200" height="800" loading="lazy">@endif @foreach($config['items']??[] as $item)@php($product=\App\Domain\Catalog\Models\Product::published()->find($item['product_id']??0))@if($product)<a href="{{ route('product',$product->slug) }}" style="left:{{ (int)($item['x']??50) }}%;top:{{ (int)($item['y']??50) }}%" class="absolute flex h-11 w-11 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full bg-white text-xl shadow" aria-label="{{ $product->name }}">+</a>@endif @endforeach</div>
@else
<div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">@foreach($config['items']??[] as $item)<article class="space-y-4 rounded-2xl border bg-white p-6">
@if(!empty($item['image']))<img src="{{ $image($item['image']) }}" alt="{{ $text($item['title']??'') }}" class="aspect-[4/3] w-full rounded-xl object-cover" width="600" height="450" loading="lazy">@endif
@if($type==='video_gallery'&&($embed=$video($item['video_url']??null)))<iframe src="{{ $embed }}" title="{{ $text($item['title']??$section->title) }}" loading="lazy" sandbox="allow-scripts allow-same-origin allow-presentation" allowfullscreen class="aspect-video w-full border-0"></iframe>@endif
<h3 class="text-xl font-semibold">{{ $text($item['title']??'') }}</h3><p class="whitespace-pre-line">{{ $text($item['body']??'') }}</p>
@if($type==='shop_by_price' && isset($item['price']))<a class="btn-secondary" href="{{ route('shop',['max'=>number_format((int)$item['price']/100,2,'.','')]) }}">{{ __('content.under') }} {{ catalog_money((int)$item['price']) }}</a>@elseif(!empty($item['url']))<a class="btn-secondary" href="{{ $item['url'] }}" @if(in_array($type,['store_locations','social_grid']))target="_blank" rel="noopener noreferrer"@endif>{{ $type==='store_locations'?__('content.map'):__('content.explore') }}</a>@endif</article>@endforeach</div>
@endif
</section>
