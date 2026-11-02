<?php

namespace App\Domain\Settings\Policies;

use App\Domain\Settings\Models\Setting;
use App\Models\Admin;

class SettingPolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $admin->is_active && $admin->can('settings.manage');
    }

    public function update(Admin $admin, Setting $setting): bool
    {
        return $this->viewAny($admin);
    }

    public function create(Admin $admin): bool
    {
        return $this->viewAny($admin);
    }

    public function delete(Admin $admin, Setting $setting): bool
    {
        return false;
    }
}
