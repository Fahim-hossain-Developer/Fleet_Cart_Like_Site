<?php

namespace App\Domain\Settings\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationConnection extends Model
{
    protected $table = 'integration_connections';

    protected $guarded = ['id'];

    protected $casts = ['config' => 'array', 'is_enabled' => 'boolean'];

    protected $hidden = [];
}
