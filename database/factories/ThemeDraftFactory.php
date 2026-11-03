<?php

namespace Database\Factories;

use App\Domain\Settings\Models\ThemeDraft;
use App\Domain\Settings\Theme\ThemeConfig;
use App\Models\Admin;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ThemeDraft> */
class ThemeDraftFactory extends Factory
{
    protected $model = ThemeDraft::class;

    public function definition(): array
    {
        return ['uuid' => (string) Str::uuid(), 'admin_id' => Admin::create(['name' => 'Theme editor', 'email' => fake()->unique()->safeEmail(), 'password' => 'test-password'])->id, 'config' => app(ThemeConfig::class)->defaults(), 'base_revision' => 0, 'expires_at' => now()->addHour()];
    }
}
