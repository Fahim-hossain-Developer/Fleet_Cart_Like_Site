<?php

namespace App\Domain\Marketing\Models;

use Database\Factories\TrackingConsentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrackingConsent extends Model
{
    /** @use HasFactory<TrackingConsentFactory> */
    use HasFactory;

    protected static function newFactory(): TrackingConsentFactory
    {
        return TrackingConsentFactory::new();
    }

    protected $table = 'tracking_consents';

    protected $guarded = ['id'];

    protected $casts = ['private_data' => 'encrypted:array', 'consented_at' => 'datetime', 'revoked_at' => 'datetime', 'expires_at' => 'datetime'];

    protected $hidden = ['private_data'];
}
