<?php

namespace App\Domain\Settings\Policies;

use App\Domain\Settings\Models\ThemePreset;
use App\Models\Admin;

final class ThemePresetPolicy
{
    public function viewAny(Admin $a): bool
    {
        return $a->is_active && $a->hasPermissionTo('theme.manage', 'admin');
    }

    public function view(Admin $a, ThemePreset $record): bool
    {
        return $this->viewAny($a);
    }

    public function create(Admin $a): bool
    {
        return $this->viewAny($a);
    }

    public function update(Admin $a, ThemePreset $record): bool
    {
        return $this->view($a, $record) && ! $record->is_system;
    }

    public function delete(Admin $a, ThemePreset $record): bool
    {
        return $this->update($a, $record);
    }
}
