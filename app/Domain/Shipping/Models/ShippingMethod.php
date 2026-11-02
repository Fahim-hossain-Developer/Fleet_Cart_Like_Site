<?php

namespace App\Domain\Shipping\Models;

use Database\Factories\ShippingMethodFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ShippingMethod extends Model
{
    /** @use HasFactory<ShippingMethodFactory> */
    use HasFactory;

    protected static function newFactory(): ShippingMethodFactory
    {
        return ShippingMethodFactory::new();
    }

    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly($this->getFillable())->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    protected $table = 'shipping_methods';

    protected $fillable = ['shipping_zone_id', 'name', 'type', 'amount', 'free_above', 'base_weight_grams', 'step_weight_grams', 'step_amount', 'is_active'];

    protected $casts = ['name' => 'array', 'is_active' => 'boolean', 'amount' => 'integer', 'free_above' => 'integer'];
}
