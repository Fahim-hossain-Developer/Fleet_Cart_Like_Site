<?php

namespace App\Domain\Catalog\Models;

use Database\Factories\ProductOptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductOption extends CatalogModel
{
    /** @use HasFactory<ProductOptionFactory> */
    use HasFactory;

    protected static function newFactory(): ProductOptionFactory
    {
        return ProductOptionFactory::new();
    }

    /** @var list<string> */
    public $translatable = ['label'];

    protected $casts = ['required' => 'boolean', 'price' => 'integer'];

    /** @return HasMany<OptionValue, $this> */
    public function values(): HasMany
    {
        return $this->hasMany(OptionValue::class);
    }
}
