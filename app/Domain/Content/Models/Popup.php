<?php

namespace App\Domain\Content\Models;

use Database\Factories\PopupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Popup extends ContentModel
{
    /** @use HasFactory<PopupFactory> */
    use HasFactory;

    protected $table = 'popups';

    protected static function newFactory(): PopupFactory
    {
        return PopupFactory::new();
    }
}
