<?php

namespace App\Domain\Content\Models;

use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Page extends ContentModel
{
    /** @use HasFactory<PageFactory> */
    use HasFactory;

    protected $table = 'pages';

    protected static function newFactory(): PageFactory
    {
        return PageFactory::new();
    }
}
