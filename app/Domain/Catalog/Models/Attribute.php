<?php

namespace App\Domain\Catalog\Models;

use Database\Factories\AttributeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attribute extends CatalogModel
{
    /** @use HasFactory<AttributeFactory> */
    use HasFactory;

    protected static function newFactory(): AttributeFactory
    {
        return AttributeFactory::new();
    }

    /** @var list<string> */
    public $translatable = ['name'];

    protected $casts = ['is_filterable' => 'boolean'];

    /** @return HasMany<AttributeValue, $this> */
    public function values(): HasMany
    {
        return $this->hasMany(AttributeValue::class);
    }
}
