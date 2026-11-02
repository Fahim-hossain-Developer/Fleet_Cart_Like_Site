<?php

namespace App\Domain\Catalog\Models;

use Database\Factories\CatalogImportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CatalogImport extends CatalogModel
{
    /** @use HasFactory<CatalogImportFactory> */
    use HasFactory;

    protected static function newFactory(): CatalogImportFactory
    {
        return CatalogImportFactory::new();
    }

    /** @var list<string> */
    public $translatable = [];

    protected $casts = ['errors' => 'array'];
}
