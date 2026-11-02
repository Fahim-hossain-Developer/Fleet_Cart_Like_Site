<?php

namespace App\Domain\Customers\Models;

use Database\Factories\NotificationPreferenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
    /** @use HasFactory<NotificationPreferenceFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = ['is_enabled' => 'boolean', 'consented_at' => 'datetime'];

    protected $attributes = ['is_enabled' => false];

    protected static function newFactory(): NotificationPreferenceFactory
    {
        return NotificationPreferenceFactory::new();
    }
}
