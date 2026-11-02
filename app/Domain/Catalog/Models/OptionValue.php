<?php

namespace App\Domain\Catalog\Models;

use Database\Factories\OptionValueFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OptionValue extends CatalogModel
{
    /** @use HasFactory<OptionValueFactory> */
    use HasFactory;

    protected static function newFactory(): OptionValueFactory
    {
        return OptionValueFactory::new();
    }

    /** @var list<string> */
    public $translatable = ['label'];

    protected $casts = ['price' => 'integer'];
}
