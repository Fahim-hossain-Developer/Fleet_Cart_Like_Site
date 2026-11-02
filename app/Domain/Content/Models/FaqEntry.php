<?php

namespace App\Domain\Content\Models;

use Database\Factories\FaqEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FaqEntry extends ContentModel
{
    /** @use HasFactory<FaqEntryFactory> */
    use HasFactory;

    protected $table = 'faq_entries';

    protected static function newFactory(): FaqEntryFactory
    {
        return FaqEntryFactory::new();
    }
}
