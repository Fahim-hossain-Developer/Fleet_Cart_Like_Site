<?php

namespace App\Domain\Content\Models;

use Database\Factories\BannerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Banner extends ContentModel
{
    /** @use HasFactory<BannerFactory> */
    use HasFactory;

    protected $table = 'banners';

    protected static function newFactory(): BannerFactory
    {
        return BannerFactory::new();
    }
}
