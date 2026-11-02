<form method="POST" action="{{ route('product-list.store',$kind) }}">@csrf
<input type="hidden" name="product_id" value="{{ $product->id }}"><input type="hidden" name="add" value="0"><button class="btn-secondary">{{ __('marketing.remove_list') }}</button></form>
