<?php

namespace App\Domain\Content\Models;

use App\Domain\Content\Landing\LandingRules;
use Database\Factories\ProductPackageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductPackage extends Model
{
    /** @use HasFactory<ProductPackageFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = ['label' => 'array', 'qty' => 'integer', 'price' => 'integer', 'free_shipping' => 'boolean', 'is_active' => 'boolean', 'sort' => 'integer'];

    protected $attributes = ['role' => 'primary', 'qty' => 1, 'is_active' => true, 'free_shipping' => false, 'sort' => 0];

    protected $hidden = [];

    protected static function newFactory(): ProductPackageFactory
    {
        return ProductPackageFactory::new();
    }

    protected static function booted(): void
    {
        static::saving(fn (self $p) => app(LandingRules::class)->package($p));
    }
}
