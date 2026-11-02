<?php

namespace App\Domain\Orders\Models;

use Database\Factories\DownloadEntitlementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DownloadEntitlement extends Model
{
    /** @use HasFactory<DownloadEntitlementFactory> */
    use HasFactory;

    protected static function newFactory(): DownloadEntitlementFactory
    {
        return DownloadEntitlementFactory::new();
    }

    protected $table = 'download_entitlements';

    protected $guarded = ['id'];

    protected $casts = ['limit' => 'integer', 'used' => 'integer', 'enabled_at' => 'datetime', 'expires_at' => 'datetime'];
}
