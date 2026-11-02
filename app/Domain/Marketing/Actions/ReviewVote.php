<?php

namespace App\Domain\Marketing\Actions;

use App\Domain\Marketing\Models\Review;
use App\Domain\Marketing\Models\ReviewVote as Vote;
use App\Models\User;

final class ReviewVote
{
    public function set(User $u, Review $review, bool $helpful): void
    {
        app(ReviewRules::class)->owner($u);
        abort_unless($review->status === 'approved' && $review->user_id !== $u->id, 403);
        if (! $helpful) {
            Vote::where('review_id', $review->id)->where('user_id', $u->id)->delete();

            return;
        }Vote::upsert([['review_id' => $review->id, 'user_id' => $u->id, 'created_at' => now(), 'updated_at' => now()]], ['review_id', 'user_id'], ['updated_at']);
    }
}
