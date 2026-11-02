<?php

namespace App\Domain\Marketing\Models;

use Database\Factories\ReviewFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property bool $notice_pending
 * @property int $notice_attempts
 */
class Review extends Model
{
    /** @use HasFactory<ReviewFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = ['notice_pending' => 'boolean', 'notice_attempts' => 'integer', 'images' => 'array', 'rating' => 'integer', 'revision' => 'integer', 'is_verified' => 'boolean', 'approved_at' => 'datetime'];

    protected $attributes = ['notice_pending' => false, 'notice_attempts' => 0, 'revision' => 0, 'status' => 'pending', 'is_verified' => false, 'images' => '[]'];

    protected static function newFactory(): ReviewFactory
    {
        return ReviewFactory::new();
    }

    /** @return HasMany<ReviewVote,$this> */
    public function votes(): HasMany
    {
        return $this->hasMany(ReviewVote::class);
    }
}
