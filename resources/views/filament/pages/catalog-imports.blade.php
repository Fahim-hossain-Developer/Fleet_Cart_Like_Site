<x-filament-panels::page>
<p>{{ __('catalog.csv_instructions') }} <code>sku,slug,name_en,name_bn,price,qty,status</code></p>
<form wire:submit="submit">{{ $this->form }}<x-filament::button type="submit" class="mt-4">{{ __('catalog.import') }}</x-filament::button></form>
<a href="{{ route('catalog.export') }}" class="underline">{{ __('catalog.export') }}</a>
@foreach($imports as $import)<section class="rounded-xl border p-4"><h2>#{{ $import->id }} · {{ __('catalog.'.$import->status) }} · {{ $import->imported }} {{ __('catalog.imported') }}</h2>@foreach($import->errors??[] as $error)<p>{{ __('catalog.row') }} {{ $error['row'] }}: {{ implode('; ',$error['messages']) }}</p>@endforeach</section>@endforeach
</x-filament-panels::page>
