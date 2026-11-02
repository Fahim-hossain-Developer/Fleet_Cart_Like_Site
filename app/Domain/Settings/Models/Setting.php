<?php

namespace App\Domain\Settings\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/** @property string|null $encrypted_value */
class Setting extends Model
{
    protected $hidden = ['encrypted_value'];

    use LogsActivity;

    protected $fillable = ['key', 'value', 'group', 'encrypted_value'];

    protected function casts(): array
    {
        return ['value' => 'json', 'encrypted_value' => 'encrypted'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['key', 'value', 'group'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
