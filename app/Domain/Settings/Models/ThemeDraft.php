<?php

namespace App\Domain\Settings\Models;

use App\Domain\Settings\Theme\ThemeConfig;
use Database\Factories\ThemeDraftFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ThemeDraft extends Model
{
    /** @use HasFactory<ThemeDraftFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = ['config' => 'array', 'base_revision' => 'integer', 'expires_at' => 'datetime'];

    protected $attributes = [];

    protected static function newFactory(): ThemeDraftFactory
    {
        return ThemeDraftFactory::new();
    }

    protected static function booted(): void
    {
        static::saving(function (self $m) {
            $m->config = app(ThemeConfig::class)->validate($m->config);
        });
    }
}
