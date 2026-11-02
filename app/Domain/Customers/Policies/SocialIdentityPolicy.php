<?php

namespace App\Domain\Customers\Policies;

use App\Domain\Customers\Models\SocialIdentity;
use App\Models\User;

final class SocialIdentityPolicy
{
    public function view(User $u, SocialIdentity $identity): bool
    {
        return ! $u->is_blocked && $identity->user_id === $u->id;
    }

    public function create(User $u): bool
    {
        return false;
    }

    public function update(User $u, SocialIdentity $identity): bool
    {
        return false;
    }

    public function delete(User $u, SocialIdentity $identity): bool
    {
        return false;
    }
}
