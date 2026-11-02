<?php

namespace App\Domain\Customers\Policies;

use App\Domain\Customers\Models\Address;
use App\Models\User;

class AddressPolicy
{
    public function view(User $u, Address $a): bool
    {
        return ! $u->is_blocked && $a->user_id === $u->id;
    }

    public function update(User $u, Address $a): bool
    {
        return $this->view($u, $a);
    }

    public function delete(User $u, Address $a): bool
    {
        return $this->view($u, $a);
    }
}
