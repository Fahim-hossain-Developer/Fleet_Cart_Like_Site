<?php

namespace App\Domain\Settings\Models;

use Database\Factories\CurrencyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Currency extends Model
{
    /** @use HasFactory<CurrencyFactory> */
    use HasFactory;

    protected static function newFactory(): CurrencyFactory
    {
        return CurrencyFactory::new();
    }

    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly($this->getFillable())->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    protected $table = 'currencies';

    protected $attributes = ['rate_ppm' => 1000000, 'decimals' => 2, 'symbol_position' => 'before', 'rounding_increment' => 1, 'markup_bps' => 0, 'is_active' => true];

    protected static function booted(): void
    {
        static::saving(function (self $currency): void {
            validator($currency->getAttributes(), ['code' => 'required|regex:/^[A-Z]{3}$/D', 'rate_ppm' => 'required|integer|min:1|max:1000000000', 'decimals' => 'required|integer|min:0|max:3', 'rounding_increment' => 'required|integer|min:1|max:10000', 'markup_bps' => 'integer|min:0|max:10000'])->validate();
            if ($currency->code === config('checkout.base_currency') && ($currency->rate_ppm !== 1000000 || $currency->decimals !== 2 || $currency->markup_bps !== 0 || $currency->rounding_increment !== 1 || ! $currency->is_active)) {
                throw ValidationException::withMessages(['currency' => __('checkout.base_currency_locked')]);
            }
        });
        static::saved(function (self $currency): void {
            if ($currency->wasRecentlyCreated || $currency->wasChanged('rate_ppm')) {
                DB::table('currency_rate_history')->insert(['currency_id' => $currency->id, 'rate_ppm' => $currency->rate_ppm, 'source' => 'manual', 'fetched_at' => now()]);
            }
        });
    }

    protected $fillable = ['code', 'symbol', 'rate_ppm', 'decimals', 'symbol_position', 'rounding_increment', 'markup_bps', 'is_active'];

    protected $casts = ['rate_ppm' => 'integer', 'decimals' => 'integer', 'rounding_increment' => 'integer', 'markup_bps' => 'integer', 'is_active' => 'boolean'];
}
