<?php

namespace App\Domain\Marketing\Policies;

use App\Domain\Marketing\Models\CartRecoveryContact;
use App\Models\Admin;

final class CartRecoveryContactPolicy
{
    public function viewAny(Admin $a): bool
    {
        return $a->is_active && $a->hasPermissionTo('marketing.manage', 'admin');
    }

    public function view(Admin $a, CartRecoveryContact $s): bool
    {
        return $this->viewAny($a);
    }

    public function create(Admin $a): bool
    {
        return false;
    }

    public function update(Admin $a, CartRecoveryContact $s): bool
    {
        return false;
    }

    public function delete(Admin $a, CartRecoveryContact $s): bool
    {
        return false;
    }
}
