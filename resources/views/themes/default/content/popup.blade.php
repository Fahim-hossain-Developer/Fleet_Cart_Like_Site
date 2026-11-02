@if($popup = app(\App\Domain\Content\Actions\StorefrontOverlay::class)->popup(request()))
<div x-data="{open:false, eligible:false, key:@js('store-popup-'.$popup->id.'-'.$popup->updated_at->timestamp),
 init(){try{let seen=Number(localStorage.getItem(this.key)||0);this.eligible=Date.now()-seen>@js((int)$popup->frequency_hours*3600000)}catch(e){this.eligible=false} @if($popup->trigger==='delay') setTimeout(()=>this.show(),@js((int)$popup->delay_seconds*1000)) @endif},
 show(){if(!this.eligible||Array.from(document.querySelectorAll('[role=dialog]')).some(el=>el.getClientRects().length&gt;0 &amp;&amp; getComputedStyle(el).visibility!=='hidden'))return;this.eligible=false;try{localStorage.setItem(this.key,String(Date.now()))}catch(e){return}this.open=true;this.$nextTick(()=>this.$refs.close.focus())},
 close(){this.open=false}
}" @mouseleave.document="if(@js($popup->trigger==='exit') &amp;&amp; $event.clientY&lt;=0 &amp;&amp; matchMedia('(pointer:fine)').matches)show()" @keydown.escape.window="close()">
<div x-show="open" x-cloak class="fixed inset-0 z-[80] flex items-center justify-center bg-black/50 p-4" @click.self="close()">
<section role="dialog" aria-modal="true" aria-labelledby="store-popup-title" x-trap.inert.noscroll="open" class="max-h-[85dvh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6">
<button type="button" x-ref="close" @click="close()" class="float-end min-h-11 rounded-lg border px-4">{{ __('store.close') }}</button>
<h2 id="store-popup-title" class="text-2xl font-semibold">{{ $popup->title }}</h2>
@if($popup->image)<img src="{{ asset('storage/'.$popup->image) }}" alt="" width="640" height="360" loading="lazy" class="mt-4 w-full rounded-lg">@endif
<div class="prose mt-4">{!! \App\Domain\Content\Actions\SafeContent::html($popup->body) !!}</div>
@if($popup->url)<a href="{{ $popup->url }}" class="mt-5 inline-flex min-h-11 items-center rounded-lg bg-brand px-5 text-white">{{ __('content.read_more') }}</a>@else @include('themes.default.marketing.newsletter') @endif
</section></div></div>
@endif
