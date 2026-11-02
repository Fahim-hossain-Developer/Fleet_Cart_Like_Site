<?php

namespace App\Domain\Catalog\Policies;

use App\Models\Admin;
use Illuminate\Database\Eloquent\Model;

class CatalogPolicy
{
    public function viewAny(Admin $a): bool
    {
        return $a->is_active && $a->hasPermissionTo('catalog.manage', 'admin');
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
}
