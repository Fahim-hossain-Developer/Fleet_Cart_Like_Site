<?php

namespace App\Domain\Content\Models;

use App\Domain\Content\Landing\LandingRules;
use Database\Factories\LandingTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LandingTemplate extends Model
{
    /** @use HasFactory<LandingTemplateFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = ['name' => 'array', 'sections' => 'array', 'form_config' => 'array', 'is_system' => 'boolean'];

    protected $attributes = ['is_system' => false];

    protected $hidden = [];

    protected static function newFactory(): LandingTemplateFactory
    {
        return LandingTemplateFactory::new();
    }

    protected static function booted(): void
    {
        static::saving(function (self $p) {
            app(LandingRules::class)->sections($p->sections);
            app(LandingRules::class)->form($p->form_config);
        });
    }
}
