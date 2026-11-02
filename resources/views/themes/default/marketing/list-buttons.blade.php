<div class="flex flex-wrap gap-3">@foreach(['wishlist','compare'] as $kind)<form method="POST" action="{{ route('product-list.store',$kind) }}">@csrf
<input type="hidden" name="product_id" value="{{ $product->id }}"><input type="hidden" name="add" value="1"><button class="btn-secondary" type="submit">{{ __('marketing.add_'.$kind) }}</button></form>@endforeach</div>
