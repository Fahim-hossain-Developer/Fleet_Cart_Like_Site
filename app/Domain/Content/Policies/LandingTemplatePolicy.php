<?php

namespace App\Domain\Content\Policies;

use App\Domain\Content\Models\LandingTemplate;
use App\Models\Admin;

final class LandingTemplatePolicy
{
    public function viewAny(Admin $a): bool
    {
        return $a->is_active && $a->hasPermissionTo('landing.manage', 'admin');
    }

    public function view(Admin $a, LandingTemplate $record): bool
    {
        return $this->viewAny($a);
    }

    public function create(Admin $a): bool
    {
        return $this->viewAny($a);
    }

    public function update(Admin $a, LandingTemplate $record): bool
    {
        return $this->view($a, $record) && ! $record->is_system;
    }

    public function delete(Admin $a, LandingTemplate $record): bool
    {
        return $this->update($a, $record);
    }
}
