<?php

namespace App\Domain\Shipping\Models;

use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Location extends Model
{
    /** @use HasFactory<LocationFactory> */
    use HasFactory;

    protected static function newFactory(): LocationFactory
    {
        return LocationFactory::new();
    }

    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly($this->getFillable())->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    protected $table = 'locations';

    protected $fillable = ['code', 'country', 'type', 'parent_id', 'name'];

    protected $casts = ['name' => 'array'];
}
