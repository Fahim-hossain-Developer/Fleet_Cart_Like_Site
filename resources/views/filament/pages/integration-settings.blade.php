<x-filament-panels::page>
<div class="space-y-4">
@if($errors->any())<ul class="text-red-600">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif<label for="provider">{{ __('integrations.provider') }}</label><select id="provider" wire:model.live="driver" class="rounded-lg border-gray-300 bg-white text-gray-900 dark:bg-gray-900 dark:text-white">@foreach(\App\Domain\Settings\Actions\IntegrationConfiguration::PROVIDERS as $key=>$meta)<option value="{{ $key }}">{{ __('integrations.provider_'.$key) }}</option>@endforeach</select>
<p>{{ __('integrations.credentials_notice') }}</p>
@if(!\App\Domain\Settings\Actions\IntegrationConfiguration::PROVIDERS[$driver]['implemented'])<p class="rounded-lg bg-amber-50 p-4 text-amber-900">{{ __('integrations.protocol_pending') }}</p>@endif
<p>{{ __('integrations.readiness') }}: {{ __(app(\App\Domain\Settings\Actions\IntegrationConfiguration::class)->ready($driver)?'integrations.ready':'integrations.inactive') }}</p>
@foreach($this->masks() as $key=>$mask)<p>{{ __('integrations.credential_'.$key) }}: {{ $mask }}</p>@endforeach
</div><form wire:submit="submit" class="space-y-6">{{ $this->form }}<x-filament::button type="submit" wire:loading.attr="disabled">{{ __('catalog.save') }}</x-filament::button></form>
</x-filament-panels::page>
