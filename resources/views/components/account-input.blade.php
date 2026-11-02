@props(['name','label'=>null,'type'=>'text','value'=>null,'required'=>false,'id'=>null])
@php($inputId=$id??$name.'-'.\Illuminate\Support\Str::random(8))
<label class="block space-y-2" for="{{ $inputId }}"><span>{{ $label ?? __('orders.'.$name) }}</span><input id="{{ $inputId }}" name="{{ $name }}" type="{{ $type }}" value="{{ $type==='password' ? '' : old($name,$value) }}" @required($required) {{ $attributes->class(['min-h-11 w-full rounded-lg border border-stone-300 bg-white px-3 py-2']) }}></label>
