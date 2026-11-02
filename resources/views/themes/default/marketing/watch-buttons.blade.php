@auth('web')<div class="mt-5 flex flex-wrap gap-3">@foreach(['stock','price'] as $kind)<form method="POST" action="{{ route('product.watch',$product->id) }}">@csrf
<input type="hidden" name="kind" value="{{ $kind }}"><input type="hidden" name="enabled" value="1"><button class="btn-secondary">{{ __('marketing.watch_'.$kind) }}</button></form>@endforeach</div>@endauth
