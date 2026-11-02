<?php

namespace App\Domain\Customers\Policies;

use App\Models\Admin;

final class NoticeDeliveryPolicy
{
    public function viewAny(Admin $a): bool
    {
        return $a->is_active && $a->hasPermissionTo('integrations.manage', 'admin');
    }

    public function create(Admin $a): bool
    {
        return false;
    }
}
