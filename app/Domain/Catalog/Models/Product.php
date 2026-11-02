<?php

namespace App\Domain\Catalog\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Product extends CatalogModel implements HasMedia
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    use InteractsWithMedia, SoftDeletes;

    /** @var list<string> */
    public $translatable = ['name', 'short_desc', 'description'];

    protected $attributes = ['status' => 'draft', 'visibility' => 'visible', 'type' => 'simple', 'manage_stock' => true, 'qty' => 0, 'stock_status' => 'in_stock', 'price' => 0, 'low_stock_at' => 5, 'views' => 0];

    protected $hidden = ['cost', 'search_text'];

    protected static function newFactory(): ProductFactory
    {
        return ProductFactory::new();
    }

    protected $casts = ['seo' => 'array', 'price' => 'integer', 'special_price' => 'integer', 'cost' => 'integer', 'qty' => 'integer', 'manage_stock' => 'boolean', 'is_featured' => 'boolean', 'free_shipping' => 'boolean', 'is_preorder' => 'boolean', 'special_from' => 'datetime', 'special_to' => 'datetime', 'new_until' => 'datetime'];

    protected static function booted(): void
    {
        static::saving(function (self $p): void {
            $p->search_text = implode(' ', array_values($p->getTranslations('name'))).' '.strip_tags(implode(' ', array_values($p->getTranslations('description')))).' '.$p->sku;
        });
        static::updating(function (self $p): void {
            if ($p->isDirty('slug')) {
                $from = '/product/'.$p->getOriginal('slug');
                $to = '/product/'.$p->slug;
                DB::table('redirects')->where('from', $to)->delete();
                DB::table('redirects')->where('to', $from)->update(['to' => $to]);
                DB::table('redirects')->updateOrInsert(['from' => $from], ['to' => $to, 'code' => 301, 'created_at' => now(), 'updated_at' => now()]);
            }
        });
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'active')->where('visibility', 'visible');
    }

    /** @return BelongsTo<Brand,$this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /** @return BelongsTo<AttributeSet,$this> */
    public function attributeSet(): BelongsTo
    {
        return $this->belongsTo(AttributeSet::class);
    }

    /** @return BelongsToMany<Category,$this> */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    /** @return BelongsToMany<Tag,$this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'tag_product');
    }

    /** @return BelongsToMany<AttributeValue,$this> */
    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(AttributeValue::class);
    }

    /** @return BelongsToMany<Badge,$this> */
    public function badges(): BelongsToMany
    {
        return $this->belongsToMany(Badge::class);
    }

    /** @return HasMany<ProductVariant,$this> */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /** @return HasMany<ProductOption,$this> */
    public function options(): HasMany
    {
        return $this->hasMany(ProductOption::class)->orderBy('sort');
    }

    /** @return HasMany<ProductDownload,$this> */
    public function downloads(): HasMany
    {
        return $this->hasMany(ProductDownload::class);
    }

    /** @return HasMany<ProductRelation,$this> */
    public function relations(): HasMany
    {
        return $this->hasMany(ProductRelation::class);
    }

    /** @return HasMany<ProductTab,$this> */
    public function tabs(): HasMany
    {
        return $this->hasMany(ProductTab::class)->orderBy('sort');
    }

    /** @return BelongsToMany<ProductTab,$this> */
    public function hiddenTabs(): BelongsToMany
    {
        return $this->belongsToMany(ProductTab::class, 'hidden_product_tabs');
    }

    public function currentPrice(): int
    {
        return $this->special_price !== null && (! $this->special_from || $this->special_from->isPast()) && (! $this->special_to || $this->special_to->isFuture()) ? $this->special_price : $this->price;
    }

    public function displayPrice(): int
    {
        if ($this->type === 'variable') {
            return (int) ($this->variants->where('is_active', true)->map(fn ($v) => $v->special_price ?? $v->price)->min() ?? $this->currentPrice());
        }

        return $this->currentPrice();
    }

    public function available(): bool
    {
        if ($this->type === 'variable') {
            return $this->variants->contains(fn ($v) => $v->is_active && $v->qty > 0);
        }

        return $this->stock_status !== 'out_of_stock' && (! $this->manage_stock || $this->qty > 0 || $this->stock_status === 'backorder');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('images')->useDisk('public')->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/avif']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $card = $this->addMediaConversion('card');
        $card->width(640)->height(640)->format('webp');
        $card->withResponsiveImages()->nonQueued();
        $modern = $this->addMediaConversion('modern');
        $modern->width(1200)->format('avif');
        $modern->nonQueued();
    }
}
