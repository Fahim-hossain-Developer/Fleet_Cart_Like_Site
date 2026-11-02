<?php

namespace App\Domain\Orders\Policies;

use App\Domain\Orders\Models\Order;
use App\Models\Admin;
use App\Models\User;

class OrderPolicy
{
    public function viewAny(Admin $a): bool
    {
        return $a->is_active && $a->hasPermissionTo('orders.manage', 'admin');
    }

    public function create(Admin $a): bool
    {
        return $this->viewAny($a);
    }

    public function view(Admin|User $u, Order $o): bool
    {
        return $u instanceof Admin ? $this->viewAny($u) : (! $u->is_blocked && $o->user_id === $u->id);
    }

    public function update(Admin $a, Order $o): bool
    {
        return $this->viewAny($a);
    }

    public function delete(Admin $a, Order $o): bool
    {
        return false;
    }
}
