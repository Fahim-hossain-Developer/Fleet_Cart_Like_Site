<?php

namespace App\Domain\Settings\Models;

use App\Domain\Settings\Theme\ThemeConfig;
use Database\Factories\ThemePresetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ThemePreset extends Model
{
    /** @use HasFactory<ThemePresetFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = ['name' => 'array', 'config' => 'array', 'is_system' => 'boolean'];

    protected $attributes = ['is_system' => false];

    protected static function newFactory(): ThemePresetFactory
    {
        return ThemePresetFactory::new();
    }

    protected static function booted(): void
    {
        static::saving(function (self $m) {
            $m->config = app(ThemeConfig::class)->validate($m->config);
        });
    }
}
