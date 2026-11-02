<?php

namespace App\Domain\Content\Models;

use Database\Factories\SlideFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Slide extends ContentModel
{
    /** @use HasFactory<SlideFactory> */
    use HasFactory;

    protected $table = 'slides';

    protected static function newFactory(): SlideFactory
    {
        return SlideFactory::new();
    }
}
