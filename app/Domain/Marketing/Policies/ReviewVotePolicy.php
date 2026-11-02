<?php

namespace App\Domain\Marketing\Policies;

use App\Domain\Marketing\Models\ReviewVote;
use App\Models\User;

final class ReviewVotePolicy
{
    public function view(User $u, ReviewVote $m): bool
    {
        return ! $u->is_blocked && $m->user_id === $u->id;
    }

    public function update(User $u, ReviewVote $m): bool
    {
        return $this->view($u, $m);
    }

    public function delete(User $u, ReviewVote $m): bool
    {
        return $this->view($u, $m);
    }
}
