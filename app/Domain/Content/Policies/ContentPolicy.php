<?php

namespace App\Domain\Content\Policies;

use App\Models\Admin;
use Illuminate\Database\Eloquent\Model;

final class ContentPolicy
{
    public function viewAny(Admin $a): bool
    {
        return $a->is_active && $a->hasPermissionTo('content.manage', 'admin');
    }

    public function view(Admin $a, Model $m): bool
    {
        return $this->viewAny($a);
    }

    public function create(Admin $a): bool
    {
        return $this->viewAny($a);
    }

    public function update(Admin $a, Model $m): bool
    {
        return $this->viewAny($a);
    }

    public function delete(Admin $a, Model $m): bool
    {
        return $this->viewAny($a);
    }

    public function deleteAny(Admin $a): bool
    {
        return $this->viewAny($a);
    }

    public function reorder(Admin $a): bool
    {
        return $this->viewAny($a);
    }
}
