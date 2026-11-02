<?php

namespace App\Domain\Catalog\Models;

use Database\Factories\ProductDownloadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductDownload extends CatalogModel
{
    /** @use HasFactory<ProductDownloadFactory> */
    use HasFactory;

    protected static function newFactory(): ProductDownloadFactory
    {
        return ProductDownloadFactory::new();
    }

    /** @var list<string> */
    public $translatable = [];

    protected $casts = [];
}
