<?php

namespace App\Domain\Orders\Actions;

use App\Domain\Orders\Models\Order;
use App\Models\Admin;
use App\Models\User;

final class OrderAccess
{
    public function staff(string $permission = 'orders.manage'): Admin
    {
        $a = auth('admin')->user();
        abort_unless($a instanceof Admin && $a->is_active && $a->hasPermissionTo($permission, 'admin'), 403);

        return $a;
    }

    public function owner(Order $order): User
    {
        $u = auth('web')->user();
        abort_unless($u instanceof User && ! $u->is_blocked && $order->user_id === $u->id, 403);

        return $u;
    }
}
