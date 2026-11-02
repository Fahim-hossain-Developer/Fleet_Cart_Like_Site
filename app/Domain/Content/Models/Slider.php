<?php

namespace App\Domain\Content\Models;

use Database\Factories\SliderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Slider extends ContentModel
{
    /** @use HasFactory<SliderFactory> */
    use HasFactory;

    protected $table = 'sliders';

    protected static function newFactory(): SliderFactory
    {
        return SliderFactory::new();
    }

    /** @return HasMany<Slide,$this> */
    public function slides(): HasMany
    {
        return $this->hasMany(Slide::class)->orderBy('sort')->orderBy('id');
    }
}
