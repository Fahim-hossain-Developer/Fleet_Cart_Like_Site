<?php

namespace App\Domain\Settings\Theme;

use App\Domain\Settings\Models\Setting;
use App\Domain\Settings\Models\ThemeDraft;
use App\Domain\Settings\Models\ThemePreset;
use App\Models\Admin;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class Theme
{
    public function staff(): Admin
    {
        $a = auth('admin')->user();
        abort_unless($a instanceof Admin && $a->is_active && $a->hasPermissionTo('theme.manage', 'admin'), 403);

        return $a;
    }

    /** @return array{config:array<string,mixed>,revision:int} */
    public function state(): array
    {
        $s = store_setting('appearance_theme', []);

        return ['config' => array_replace(app(ThemeConfig::class)->defaults(), $s['config'] ?? []), 'revision' => (int) ($s['revision'] ?? 0)];
    }

    /** @return array<string,mixed> */
    public function current(): array
    {
        $uuid = request()->query('theme_preview');
        if (is_string($uuid)) {
            $a = $this->staff();
            abort_unless(request()->hasValidSignature(), 403);
            $d = ThemeDraft::where('uuid', $uuid)->where('admin_id', $a->id)->where('expires_at', '>', now())->firstOrFail();

            return $d->config;
        }

        return $this->state()['config'];
    }

    /** @param array<string,mixed> $config */
    public function draft(array $config, int $revision): ThemeDraft
    {
        return ThemeDraft::create(['uuid' => (string) Str::uuid(), 'admin_id' => $this->staff()->id, 'config' => app(ThemeConfig::class)->validate($config), 'base_revision' => $revision, 'expires_at' => now()->addHours(2)]);
    }

    public function previewUrl(ThemeDraft $draft, string $page = 'home'): string
    {
        abort_unless($draft->admin_id === $this->staff()->id, 403);
        abort_unless(in_array($page, ['home', 'shop'], true), 422);

        return URL::temporarySignedRoute($page, $draft->expires_at, ['theme_preview' => $draft->uuid]);
    }

    /** @param array<string,mixed> $config */
    public function publish(array $config, int $expectedRevision): int
    {
        $a = $this->staff();
        $config = app(ThemeConfig::class)->validate($config);

        return DB::transaction(function () use ($a, $config, $expectedRevision) {
            Setting::upsert([['key' => 'appearance_theme', 'group' => 'appearance', 'value' => '{}', 'created_at' => now(), 'updated_at' => now()]], ['key'], ['updated_at']);
            $row = Setting::where('key', 'appearance_theme')->lockForUpdate()->firstOrFail();
            $stored = $row->value;
            $revision = is_array($stored) ? (int) ($stored['revision'] ?? 0) : 0;
            if ($revision !== $expectedRevision) {
                throw ValidationException::withMessages(['config' => __('appearance.conflict')]);
            }
            $row->update(['value' => ['config' => $config, 'revision' => $revision + 1]]);
            DB::afterCommit(fn () => Cache::forget('store.setting.appearance_theme'));
            activity()->causedBy($a)->withProperties(['revision' => $revision + 1])->log('theme_published');

            return $revision + 1;
        }, 3);
    }

    /** @param array<string,mixed> $config */
    public function savePreset(array $config, string $name): ThemePreset
    {
        $a = $this->staff();
        validator(compact('name'), ['name' => 'required|string|max:100'])->validate();
        $p = ThemePreset::create(['name' => ['en' => $name, 'bn' => $name], 'slug' => Str::slug($name).'-'.strtolower(Str::random(8)), 'config' => app(ThemeConfig::class)->validate($config)]);
        activity()->causedBy($a)->performedOn($p)->log('theme_preset_created');

        return $p;
    }
}
