@php($flash=($product->type==='variable'&&!$selected)?null:app(\App\Domain\Marketing\Actions\FlashPricing::class)->quote($product,$selected,$selected?($selected->special_price??$selected->price):$product->basePrice())['item'])
@if($flash)
<div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 p-4" wire:key="flash-status-{{ $flash->id }}" x-data="{remaining:0,timer:null,init(){let update=()=>this.remaining=Math.max(0,Math.floor((@js($flash->sale->ends_at->timestamp*1000)-Date.now())/1000));update();this.timer=setInterval(update,1000)},destroy(){clearInterval(this.timer)}}">
<p>{{ __('marketing.flash_units',['count'=>max(0,$flash->quota-$flash->sold)]) }}</p>
<p class="mt-2 text-sm">{{ __('content.ends_at') }}: <time datetime="{{ $flash->sale->ends_at->toAtomString() }}">{{ $flash->sale->ends_at->format('d M Y H:i T') }}</time></p><p class="mt-2 font-mono" x-show="remaining>0" x-text="Math.floor(remaining/3600)+':'+String(Math.floor(remaining%3600/60)).padStart(2,'0')+':'+String(remaining%60).padStart(2,'0')"></p><p x-show="remaining===0" x-cloak>{{ __('marketing.flash_refresh') }}</p>
</div>
@endif
