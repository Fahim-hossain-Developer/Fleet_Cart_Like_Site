<?php

namespace App\Domain\Checkout\Models;

use Database\Factories\TaxRateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class TaxRate extends Model
{
    /** @use HasFactory<TaxRateFactory> */
    use HasFactory;

    protected static function newFactory(): TaxRateFactory
    {
        return TaxRateFactory::new();
    }

    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly($this->getFillable())->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    protected $table = 'tax_rates';

    protected $fillable = ['tax_class_id', 'country', 'district_id', 'rate_bps', 'inclusive', 'is_active'];

    protected $casts = ['is_active' => 'boolean', 'inclusive' => 'boolean', 'rate_bps' => 'integer'];
}
