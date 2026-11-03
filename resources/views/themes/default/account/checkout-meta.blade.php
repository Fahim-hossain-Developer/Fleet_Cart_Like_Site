@foreach(['alternate_phone','delivery_note','preferred_time','custom_field'] as $field)
@if(!empty($order->checkout_meta[$field]))<p><strong>{{ $field==='custom_field'?($order->checkout_meta['custom_label'][app()->getLocale()]??$order->checkout_meta['custom_label']['en']??__('landing.custom_field')):__('landing.'.$field) }}:</strong> {{ $order->checkout_meta[$field] }}</p>@endif
@endforeach
