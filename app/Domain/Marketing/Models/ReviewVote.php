<?php

namespace App\Domain\Marketing\Models;

use Database\Factories\ReviewVoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReviewVote extends Model
{
    /** @use HasFactory<ReviewVoteFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [];

    protected $attributes = [];

    protected static function newFactory(): ReviewVoteFactory
    {
        return ReviewVoteFactory::new();
    }
}
