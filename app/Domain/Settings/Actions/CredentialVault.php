<?php

namespace App\Domain\Settings\Actions;

use App\Domain\Settings\Models\Setting;

final class CredentialVault
{
    public function replace(string $key, string $credential): void
    {
        Setting::updateOrCreate(['key' => 'credential.'.$key], ['group' => 'credentials', 'value' => null, 'encrypted_value' => $credential]);
    }

    public function get(string $key): ?string
    {
        return Setting::where('key', 'credential.'.$key)->first()?->encrypted_value;
    }

    public function masked(string $key): string
    {
        $value = $this->get($key);

        return $value === null ? '' : '••••'.substr($value, -4);
    }
}
