<?php

namespace App\Domain\Shipping\Models;

use Database\Factories\ShippingZoneFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ShippingZone extends Model
{
    /** @use HasFactory<ShippingZoneFactory> */
    use HasFactory;

    protected static function newFactory(): ShippingZoneFactory
    {
        return ShippingZoneFactory::new();
    }

    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly($this->getFillable())->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    protected $table = 'shipping_zones';

    protected $fillable = ['name', 'country', 'district_ids', 'is_active'];

    protected $casts = ['name' => 'array', 'district_ids' => 'array', 'is_active' => 'boolean'];
}
