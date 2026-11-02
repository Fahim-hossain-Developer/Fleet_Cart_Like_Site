<?php

namespace App\Domain\Catalog\Models;

use Database\Factories\AttributeSetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AttributeSet extends CatalogModel
{
    /** @use HasFactory<AttributeSetFactory> */
    use HasFactory;

    protected static function newFactory(): AttributeSetFactory
    {
        return AttributeSetFactory::new();
    }

    /** @var list<string> */
    public $translatable = ['name', 'description'];

    protected $casts = ['is_active' => 'boolean'];

    /** @return BelongsToMany<Attribute, $this> */
    public function attributes(): BelongsToMany
    {
        return $this->belongsToMany(Attribute::class);
    }
}
