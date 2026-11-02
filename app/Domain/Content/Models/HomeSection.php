<?php

namespace App\Domain\Content\Models;

use Database\Factories\HomeSectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class HomeSection extends ContentModel
{
    /** @use HasFactory<HomeSectionFactory> */
    use HasFactory;

    protected $table = 'home_sections';

    protected static function newFactory(): HomeSectionFactory
    {
        return HomeSectionFactory::new();
    }
}
