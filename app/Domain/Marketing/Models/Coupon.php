<?php

namespace App\Domain\Marketing\Models;

use Database\Factories\CouponFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Coupon extends Model
{
    /** @use HasFactory<CouponFactory> */
    use HasFactory;

    protected static function newFactory(): CouponFactory
    {
        return CouponFactory::new();
    }

    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly($this->getFillable())->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    protected $table = 'coupons';

    protected static function booted(): void
    {
        static::saving(function (self $coupon): void {
            validator($coupon->getAttributes(), ['type' => 'required|in:fixed,percent,free_shipping', 'value' => 'required|integer|min:0|max:'.($coupon->type === 'percent' ? 100 : 100000000000)])->validate();
        });
    }

    protected $fillable = ['code', 'type', 'value', 'minimum', 'max_discount', 'usage_limit', 'per_customer_limit', 'used', 'starts_at', 'ends_at', 'is_active'];

    protected $casts = ['is_public' => 'boolean', 'value' => 'integer', 'minimum' => 'integer', 'max_discount' => 'integer', 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'is_active' => 'boolean'];
}
