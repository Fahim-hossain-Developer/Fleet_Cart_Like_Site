<?php

namespace App\Domain\Marketing\Policies;

use App\Models\Admin;

final class ReviewRequestPolicy
{
    public function viewAny(Admin $a): bool
    {
        return $a->is_active && $a->hasPermissionTo('marketing.manage', 'admin');
    }

    public function create(Admin $a): bool
    {
        return false;
    }
}
