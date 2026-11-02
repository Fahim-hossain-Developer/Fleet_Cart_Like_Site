<!DOCTYPE html><html lang="{{ app()->getLocale() }}"><head><meta charset="utf-8"><style>
@font-face{font-family:NotoBengali;src:url('{{ public_path('fonts/NotoSansBengali-Regular.ttf') }}') format('truetype')}body{font-family:DejaVu Sans,sans-serif;font-size:10pt;color:#202825}h1{font-size:22pt}table{width:100%;border-collapse:collapse;margin:20px 0}th,td{text-align:left;padding:9px;border-bottom:1px solid #ddd}small{font-size:8pt} .muted{color:#555}.money{text-align:right}.gift{border:1px solid #aaa;padding:12px}
</style></head><body>
@php($t=app(\App\Domain\Orders\Actions\PdfText::class))
<h1>{{ $t->render(__('orders.'.$type)) }}</h1><p>{{ $t->render(store_setting('name')) }}</p><strong>{{ $order->number }}</strong><p>{{ $order->placed_at->format('d M Y') }} · {{ $t->render(__('orders.status_'.$order->status)) }}</p>
<p>{{ $t->render($order->recipient_name ?: $order->shipping_address['name']) }}<br>{{ $order->recipient_phone ?: $order->phone }}</p>
<p>@foreach($order->shipping_address as $key=>$part)@if($key!=='name'){{ $t->render(is_array($part)?($part[app()->getLocale()]??$part['en']??''):$part) }}<br>@endif @endforeach</p>
@if($type!=='label')<table><thead><tr><th>{{ $t->render(__('orders.item')) }}</th><th>{{ $t->render(__('orders.qty')) }}</th>@if($type==='invoice'&&!$order->hide_prices)<th class="money">{{ $t->render(__('orders.total')) }} ({{ $order->currency }})</th>@endif</tr></thead><tbody>
@foreach($order->items as $i)<tr><td>{{ $t->render($i->name) }}<br><small>{{ $i->sku }}</small>@foreach($i->options['labels']??[] as $option)<br><small>{{ $t->render($option['label'].': '.$option['value']) }}</small>@endforeach</td><td>{{ $i->qty }}</td>@if($type==='invoice'&&!$order->hide_prices)<td class="money">{{ number_format($i->total/100,2) }}</td>@endif</tr>@endforeach</tbody></table>
@if($type==='invoice'&&!$order->hide_prices)<table>@foreach(['subtotal','discount','shipping','gift_fee','tax','tax_included','total','paid_amount','refunded_amount'] as $key)<tr><td>{{ $t->render(__('orders.'.$key)) }}</td><td class="money">{{ $order->currency }} {{ number_format(($key==='tax'?$order->tax-$order->tax_included:$order->$key)/100,2) }}</td></tr>@endforeach</table><p>{{ $t->render(__('orders.payment_'.$order->payment_status)) }}</p>@endif
@if($order->hide_prices)<p>{{ $t->render(__('orders.prices_hidden')) }}</p>@endif
@endif
@if($order->is_gift)<div class="gift"><strong>{{ $t->render(__('orders.gift')) }}</strong><p>{{ $t->render($order->gift_message) }}</p></div>@endif
</body></html>