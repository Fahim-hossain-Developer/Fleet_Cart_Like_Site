<?php

namespace App\Domain\Catalog\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class Category extends CatalogModel
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    protected static function newFactory(): CategoryFactory
    {
        return CategoryFactory::new();
    }

    /** @var list<string> */
    public $translatable = ['name', 'description'];

    protected $casts = ['seo' => 'array', 'is_active' => 'boolean', 'show_in_menu' => 'boolean'];

    protected static function booted(): void
    {
        static::saving(function (self $category): void {
            $parent = $category->parent_id;
            $seen = [];
            while ($parent) {
                if ((int) $parent === (int) $category->id || in_array($parent, $seen, true)) {
                    throw ValidationException::withMessages(['parent_id' => __('catalog.category_cycle')]);
                }
                $seen[] = $parent;
                $parent = self::query()->whereKey($parent)->value('parent_id');
            }
        });
    }

    /** @return BelongsTo<Category, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<Category, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort');
    }

    /** @return BelongsToMany<Product, $this> */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    /** @return BelongsToMany<Badge, $this> */
    public function badges(): BelongsToMany
    {
        return $this->belongsToMany(Badge::class);
    }
}
