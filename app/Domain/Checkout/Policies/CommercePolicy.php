<?php

namespace App\Domain\Checkout\Policies;

use App\Domain\Orders\Models\Order;
use App\Models\Admin;
use Illuminate\Database\Eloquent\Model;

class CommercePolicy
{
    protected string $permission = 'checkout.manage';

    public function viewAny(Admin $admin): bool
    {
        return $admin->is_active && $admin->hasPermissionTo($this->permission, 'admin');
    }

    public function create(Admin $admin): bool
    {
        return $this->viewAny($admin);
    }

    public function view(Admin $admin, Model $model): bool
    {
        return $this->viewAny($admin);
    }

    public function update(Admin $admin, Model $model): bool
    {
        return ! ($model instanceof Order) && $this->viewAny($admin);
    }

    public function delete(Admin $admin, Model $model): bool
    {
        return ! ($model instanceof Order) && $this->viewAny($admin);
    }
}
