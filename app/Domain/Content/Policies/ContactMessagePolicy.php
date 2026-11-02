<?php

namespace App\Domain\Content\Policies;

use App\Domain\Content\Models\ContactMessage;
use App\Models\Admin;

final class ContactMessagePolicy
{
    public function viewAny(Admin $a): bool
    {
        return $a->is_active && $a->hasPermissionTo('messages.manage', 'admin');
    }

    public function view(Admin $a, ContactMessage $m): bool
    {
        return $this->viewAny($a);
    }

    public function create(Admin $a): bool
    {
        return false;
    }

    public function update(Admin $a, ContactMessage $m): bool
    {
        return $this->viewAny($a);
    }

    public function delete(Admin $a, ContactMessage $m): bool
    {
        return $this->viewAny($a);
    }
}
