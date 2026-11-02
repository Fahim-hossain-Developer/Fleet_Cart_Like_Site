<?php

namespace App\Domain\Marketing\Models;

use Database\Factories\FlashAllocationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FlashAllocation extends Model
{
    /** @use HasFactory<FlashAllocationFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = ['qty' => 'integer', 'released' => 'integer'];

    protected $attributes = ['released' => 0];

    protected static function newFactory(): FlashAllocationFactory
    {
        return FlashAllocationFactory::new();
    }
}
