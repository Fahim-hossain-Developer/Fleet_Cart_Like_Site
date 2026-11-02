<?php

namespace App\Domain\Customers\Policies;

use App\Domain\Customers\Models\StoreNotification;
use App\Models\User;

final class StoreNotificationPolicy
{
    public function view(User $u, StoreNotification $n): bool
    {
        return ! $u->is_blocked && $n->notifiable_type === User::class && (int) $n->notifiable_id === $u->id;
    }

    public function update(User $u, StoreNotification $n): bool
    {
        return $this->view($u, $n);
    }

    public function delete(User $u, StoreNotification $n): bool
    {
        return $this->view($u, $n);
    }
}
