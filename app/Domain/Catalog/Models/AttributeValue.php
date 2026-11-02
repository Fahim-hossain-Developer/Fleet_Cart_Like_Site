<?php

namespace App\Domain\Catalog\Models;

use Database\Factories\AttributeValueFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttributeValue extends CatalogModel
{
    /** @use HasFactory<AttributeValueFactory> */
    use HasFactory;

    protected static function newFactory(): AttributeValueFactory
    {
        return AttributeValueFactory::new();
    }

    /** @var list<string> */
    public $translatable = ['name'];

    protected $casts = [];

    /** @return BelongsTo<Attribute, $this> */
    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }
}
