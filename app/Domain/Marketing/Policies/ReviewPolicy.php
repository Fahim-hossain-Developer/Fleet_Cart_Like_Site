<?php

namespace App\Domain\Marketing\Policies;

use App\Domain\Marketing\Models\Review;
use App\Models\Admin;
use App\Models\User;

final class ReviewPolicy
{
    public function viewAny(Admin $a): bool
    {
        return $a->is_active && $a->hasPermissionTo('marketing.manage', 'admin');
    }

    public function view(User|Admin $actor, Review $r): bool
    {
        return $actor instanceof Admin ? $this->viewAny($actor) : (! $actor->is_blocked && $r->user_id === $actor->id);
    }

    public function create(User|Admin $actor): bool
    {
        return $actor instanceof User && ! $actor->is_blocked;
    }

    public function update(User|Admin $actor, Review $r): bool
    {
        return $this->view($actor, $r);
    }

    public function delete(Admin $actor, Review $r): bool
    {
        return $this->viewAny($actor);
    }

    public function deleteAny(Admin $a): bool
    {
        return $this->viewAny($a);
    }
}
