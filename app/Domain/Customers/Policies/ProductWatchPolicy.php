<?php

namespace App\Domain\Customers\Policies;

use App\Domain\Customers\Models\ProductWatch;
use App\Models\User;

final class ProductWatchPolicy
{
    public function view(User $u, ProductWatch $m): bool
    {
        return ! $u->is_blocked && $m->user_id === $u->id;
    }

    public function create(User $u): bool
    {
        return ! $u->is_blocked;
    }

    public function update(User $u, ProductWatch $m): bool
    {
        return $this->view($u, $m);
    }

    public function delete(User $u, ProductWatch $m): bool
    {
        return $this->view($u, $m);
    }
}
