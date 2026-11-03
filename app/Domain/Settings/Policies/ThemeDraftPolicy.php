<?php

namespace App\Domain\Settings\Policies;

use App\Domain\Settings\Models\ThemeDraft;
use App\Models\Admin;

final class ThemeDraftPolicy
{
    public function viewAny(Admin $a): bool
    {
        return $a->is_active && $a->hasPermissionTo('theme.manage', 'admin');
    }

    public function view(Admin $a, ThemeDraft $record): bool
    {
        return $this->viewAny($a) && $record->admin_id === $a->id;
    }

    public function create(Admin $a): bool
    {
        return $this->viewAny($a);
    }

    public function update(Admin $a, ThemeDraft $record): bool
    {
        return $this->view($a, $record);
    }

    public function delete(Admin $a, ThemeDraft $record): bool
    {
        return $this->update($a, $record);
    }
}
