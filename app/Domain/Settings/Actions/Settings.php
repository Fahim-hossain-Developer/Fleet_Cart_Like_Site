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
        if ($key === 'tracking_ids') {
            validator(['c' => $value], ['c.ga4' => 'nullable|regex:/^G-[A-Z0-9]{4,20}$/D', 'c.gtm' => 'nullable|regex:/^GTM-[A-Z0-9]{4,20}$/D', 'c.meta' => 'nullable|regex:/^[0-9]{5,25}$/D', 'c.tiktok' => 'nullable|regex:/^[a-zA-Z0-9]{10,30}$/D'])->validate();
        }
        if ($key === 'seo_defaults') {
            validator(['c' => $value], ['c.title' => 'nullable|array:en,bn', 'c.title.*' => 'nullable|string|max:200', 'c.description' => 'nullable|array:en,bn', 'c.description.*' => 'nullable|string|max:500', 'c.schema' => 'boolean'])->validate();
        }
        if ($key === 'floating_controls') {
            validator(['c' => $value], ['c.position' => 'required|in:left,right', 'c.paths' => 'nullable|array|max:30', 'c.paths.*' => 'string|max:160|regex:~^/[a-zA-Z0-9/_*-]*$~D', 'c.exclude' => 'nullable|array|max:30', 'c.exclude.*' => 'string|max:160|regex:~^/[a-zA-Z0-9/_*-]*$~D', 'c.whatsapp' => 'array:enabled,phone,message', 'c.whatsapp.enabled' => 'boolean', 'c.whatsapp.phone' => 'nullable|regex:/^\+[1-9][0-9]{7,14}$/D', 'c.whatsapp.message' => 'nullable|string|max:300', 'c.messenger' => 'array:enabled,page', 'c.messenger.enabled' => 'boolean', 'c.messenger.page' => 'nullable|regex:/^[a-zA-Z0-9.]{3,100}$/D', 'c.call' => 'array:enabled,phone', 'c.call.enabled' => 'boolean', 'c.call.phone' => 'nullable|regex:/^\+[1-9][0-9]{7,14}$/D', 'c.back_to_top' => 'boolean', 'c.chat' => 'array:enabled,property,widget', 'c.chat.enabled' => 'boolean', 'c.chat.property' => 'nullable|regex:/^[a-f0-9]{24}$/D', 'c.chat.widget' => 'nullable|regex:/^[a-zA-Z0-9]{1,30}$/D'])->validate();
        }
        if ($key === 'social_proof') {
            validator(['c' => $value], ['c.enabled' => 'boolean', 'c.frequency' => 'required|integer|min:30|max:3600', 'c.exclude' => 'nullable|array|max:30', 'c.exclude.*' => 'string|max:160|regex:~^/[a-zA-Z0-9/_*-]*$~D'])->validate();
        }
        if ($key === 'checkout_payment_methods') {
            validator(['methods' => $value], ['methods' => 'array:cod,bank,manual_mobile', 'methods.*.enabled' => 'boolean', 'methods.*.instructions' => 'nullable|string|max:2000'])->validate();
        }
        if ($key === 'reviews_blocked_words') {
            validator(['words' => $value], ['words.*' => 'string|max:50'])->validate();
        }
        if ($key === 'admin_otp_roles') {
            validator(['roles' => $value], ['roles.*' => 'string|exists:roles,name,guard_name,admin'])->validate();
        }
        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value, 'group' => 'general']);
        Cache::forget('store.setting.'.$key);
    }
}
