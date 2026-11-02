<?php

namespace App\Domain\Marketing\Models;

use Database\Factories\TrackingEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrackingEvent extends Model
{
    /** @use HasFactory<TrackingEventFactory> */
    use HasFactory;

    protected static function newFactory(): TrackingEventFactory
    {
        return TrackingEventFactory::new();
    }

    protected $table = 'tracking_events';

    protected $guarded = ['id'];

    protected $casts = ['data' => 'array', 'occurred_at' => 'datetime', 'browser_claimed_at' => 'datetime', 'accepted_at' => 'datetime', 'attempts' => 'integer'];

    protected $hidden = ['source_key'];
}
