<?php

namespace App\Domain\Content\Models;

use Database\Factories\PopupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Popup extends ContentModel
{
    /** @use HasFactory<PopupFactory> */
    use HasFactory;

    protected $table = 'popups';

    protected $attributes = ['status' => 'draft', 'is_active' => true, 'sort' => 0, 'trigger' => 'delay', 'delay_seconds' => 15, 'frequency_hours' => 168];

    protected static function newFactory(): PopupFactory
    {
        return PopupFactory::new();
    }
}
