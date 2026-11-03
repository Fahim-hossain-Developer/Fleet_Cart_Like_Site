<?php

namespace Database\Factories;

use App\Domain\Settings\Models\ThemePreset;
use App\Domain\Settings\Theme\ThemeConfig;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ThemePreset> */
class ThemePresetFactory extends Factory
{
    protected $model = ThemePreset::class;

    public function definition(): array
    {
        return ['slug' => 'preset-'.Str::random(12), 'name' => ['en' => 'Original theme', 'bn' => 'মূল থিম'], 'config' => app(ThemeConfig::class)->defaults()];
    }
}
