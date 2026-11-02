<?php

namespace App\Domain\Customers\Policies;

use App\Models\Admin;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(Admin $a): bool
    {
        return $a->is_active && $a->hasPermissionTo('customers.view', 'admin');
    }

    public function view(Admin $a, User $u): bool
    {
        return $this->viewAny($a);
    }

    public function update(Admin $a, User $u): bool
    {
        return $a->is_active && $a->hasPermissionTo('customers.manage', 'admin');
    }

    public function create(Admin $a): bool
    {
        return false;
    }

    public function delete(Admin $a, User $u): bool
    {
        return false;
    }
}
