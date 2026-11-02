<?php

namespace Database\Factories;

use App\Domain\Marketing\Models\Review;
use App\Domain\Marketing\Models\ReviewVote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ReviewVote> */
class ReviewVoteFactory extends Factory
{
    protected $model = ReviewVote::class;

    public function definition(): array
    {
        return ['review_id' => Review::factory(), 'user_id' => User::factory()];
    }
}
