<?php

namespace App\Domain\Content\Policies;

use App\Domain\Content\Models\Redirect;
use App\Models\Admin;

final class RedirectPolicy
{
    public function viewAny(Admin $a): bool
    {
        return $a->is_active && $a->hasPermissionTo('settings.manage', 'admin');
    }

    public function view(Admin $a, Redirect $r): bool
    {
        return $this->viewAny($a);
    }

    public function create(Admin $a): bool
    {
        return $this->viewAny($a);
    }

    public function update(Admin $a, Redirect $r): bool
    {
        return $this->viewAny($a);
    }

    public function delete(Admin $a, Redirect $r): bool
    {
        return $this->viewAny($a);
    }
}
