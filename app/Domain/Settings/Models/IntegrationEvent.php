<?php

namespace App\Domain\Settings\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationEvent extends Model
{
    protected $table = 'integration_events';

    protected $guarded = ['id'];

    protected $casts = ['processed_at' => 'datetime'];

    protected $hidden = [];
}
