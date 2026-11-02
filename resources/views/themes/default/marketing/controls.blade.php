@php($floating=app(\App\Domain\Marketing\Actions\StorefrontControls::class)->floating())
@if($floating)
<div class="fixed bottom-28 z-30 flex max-w-[70vw] flex-col items-end gap-2 md:bottom-6 {{ $floating['position']==='left'?'left-4':'right-4' }}" x-data="{chatLoaded:false, loadChat(){if(this.chatLoaded)return;this.chatLoaded=true;window.Tawk_API=window.Tawk_API||{};window.Tawk_API.customStyle={visibility:{desktop:{position:'br',xOffset:20,yOffset:25},mobile:{position:'br',xOffset:20,yOffset:115}}};let script=document.createElement('script');script.async=true;script.src=@js($floating['chat']);document.head.appendChild(script)}}">
@foreach($floating['links'] as $link)<a href="{{ $link['url'] }}" @if($link['type']!=='call')target="_blank" rel="noopener noreferrer"@endif class="inline-flex min-h-11 items-center rounded-full border bg-white px-4 py-2 text-sm shadow">{{ __('marketing.control_'.$link['type']) }}</a>@endforeach
@if($floating['chat'])<button type="button" @click="loadChat()" :disabled="chatLoaded" class="min-h-11 rounded-full border bg-white px-4 py-2 text-sm shadow">{{ __('marketing.load_chat') }}</button><span class="max-w-52 rounded-lg bg-white p-2 text-xs">{{ __('marketing.chat_consent') }}</span>@endif
@if($floating['top'])<button type="button" @click="window.scrollTo({top:0,behavior:matchMedia('(prefers-reduced-motion:reduce)').matches?'instant':'smooth'})" class="min-h-11 rounded-full border bg-white px-4 py-2 text-sm shadow">{{ __('marketing.back_to_top') }}</button>@endif
</div>
@endif
@php($proof=app(\App\Domain\Marketing\Actions\StorefrontControls::class)->proof())
@if(count($proof['items']))
<aside x-data="{items:@js($proof['items']),index:0,open:false,dismissed:false,timer:null,init(){this.timer=setInterval(()=>{if(this.dismissed||document.hidden||Array.from(document.querySelectorAll('[role=dialog]')).some(el=>el.getClientRects().length&gt;0 &amp;&amp; getComputedStyle(el).visibility!=='hidden'))return;this.open=true;setTimeout(()=>{this.open=false;this.index=(this.index+1)%this.items.length},7000)},@js($proof['frequency']*1000))},destroy(){clearInterval(this.timer)}}" x-show="open" x-cloak class="fixed bottom-28 {{ ($floating['position']??'right')==='left'?'right-4':'left-4' }} z-20 max-w-[min(20rem,85vw)] rounded-xl border bg-white p-4 shadow md:bottom-6" aria-label="{{ __('marketing.recent_purchases') }}">
<button type="button" @click="open=false;dismissed=true;clearInterval(timer)" class="float-end min-h-11 min-w-11" aria-label="{{ __('store.close') }}">×</button><a :href="items[index].url" x-text="items[index].text" class="text-sm"></a>
</aside>
@endif
