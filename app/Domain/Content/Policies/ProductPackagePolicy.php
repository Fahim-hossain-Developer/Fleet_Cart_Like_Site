<?php

namespace App\Domain\Content\Policies;

use App\Domain\Content\Models\ProductPackage;
use App\Models\Admin;

final class ProductPackagePolicy
{
    public function viewAny(Admin $a): bool
    {
        return $a->is_active && $a->hasPermissionTo('landing.manage', 'admin');
    }

    public function view(Admin $a, ProductPackage $record): bool
    {
        return $this->viewAny($a);
    }

    public function create(Admin $a): bool
    {
        return $this->viewAny($a);
    }

    public function update(Admin $a, ProductPackage $record): bool
    {
        return $this->view($a, $record);
    }

    public function delete(Admin $a, ProductPackage $record): bool
    {
        return $this->update($a, $record);
    }
}
