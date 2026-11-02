<?php

namespace App\Domain\Marketing\Models;

use Database\Factories\ReviewRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReviewRequest extends Model
{
    /** @use HasFactory<ReviewRequestFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = ['sent_at' => 'datetime', 'attempts' => 'integer'];

    protected $attributes = ['attempts' => 0];

    protected static function newFactory(): ReviewRequestFactory
    {
        return ReviewRequestFactory::new();
    }
}
