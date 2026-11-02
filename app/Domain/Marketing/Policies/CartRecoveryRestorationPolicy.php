<?php

namespace App\Domain\Marketing\Policies;

use App\Domain\Marketing\Models\CartRecoveryRestoration;
use App\Models\Admin;

final class CartRecoveryRestorationPolicy
{
    public function viewAny(Admin $a): bool
    {
        return $a->is_active && $a->hasPermissionTo('marketing.manage', 'admin');
    }

    public function view(Admin $a, CartRecoveryRestoration $s): bool
    {
        return $this->viewAny($a);
    }

    public function create(Admin $a): bool
    {
        return false;
    }

    public function update(Admin $a, CartRecoveryRestoration $s): bool
    {
        return false;
    }

    public function delete(Admin $a, CartRecoveryRestoration $s): bool
    {
        return false;
    }
}
