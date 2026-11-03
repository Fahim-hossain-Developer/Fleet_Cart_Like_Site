<?php

namespace App\Domain\Content\Policies;

use App\Domain\Content\Models\LandingPage;
use App\Models\Admin;

final class LandingPagePolicy
{
    public function viewAny(Admin $a): bool
    {
        return $a->is_active && $a->hasPermissionTo('landing.manage', 'admin');
    }

    public function view(Admin $a, LandingPage $record): bool
    {
        return $this->viewAny($a);
    }

    public function create(Admin $a): bool
    {
        return $this->viewAny($a);
    }

    public function update(Admin $a, LandingPage $record): bool
    {
        return $this->view($a, $record);
    }

    public function delete(Admin $a, LandingPage $record): bool
    {
        return $this->update($a, $record);
    }
}
