<?php

namespace App\Domain\Customers\Policies;

use App\Domain\Customers\Models\NotificationPreference;
use App\Models\User;

final class NotificationPreferencePolicy
{
    public function view(User $u, NotificationPreference $m): bool
    {
        return ! $u->is_blocked && $m->user_id === $u->id;
    }

    public function create(User $u): bool
    {
        return ! $u->is_blocked;
    }

    public function update(User $u, NotificationPreference $m): bool
    {
        return $this->view($u, $m);
    }

    public function delete(User $u, NotificationPreference $m): bool
    {
        return $this->view($u, $m);
    }
}
