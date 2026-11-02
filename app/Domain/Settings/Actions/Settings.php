<?php

namespace App\Domain\Settings\Actions;

use App\Domain\Settings\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

final class Settings
{
    public function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember('store.setting.'.$key, 300, fn () => Setting::query()->where('key', $key)->first()->value ?? config('store.defaults.'.$key, $default));
    }

    public function set(string $key, mixed $value): void
    {
        $rules = config('store.rules.'.$key);
        if (! is_string($rules)) {
            throw ValidationException::withMessages(['key' => 'Unknown setting.']);
        }
        validator(['value' => $value], ['value' => $rules, ...($key === 'catalog_sidebar' ? ['value.*' => 'string|in:categories,attributes,latest,sale,top_rated,tags'] : [])])->validate();
        if ($key === 'checkout_payment_methods') {
            validator(['methods' => $value], ['methods' => 'array:cod,bank,manual_mobile', 'methods.*.enabled' => 'boolean', 'methods.*.instructions' => 'nullable|string|max:2000'])->validate();
        }
        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value, 'group' => 'general']);
        Cache::forget('store.setting.'.$key);
    }
}
