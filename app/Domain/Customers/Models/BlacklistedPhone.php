<?php

namespace App\Domain\Customers\Models;

use Database\Factories\BlacklistedPhoneFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class BlacklistedPhone extends Model
{
    /** @use HasFactory<BlacklistedPhoneFactory> */
    use HasFactory;

    protected static function newFactory(): BlacklistedPhoneFactory
    {
        return BlacklistedPhoneFactory::new();
    }

    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['phone', 'reason'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    protected $table = 'phone_blacklist';

    protected $guarded = ['id'];
}
