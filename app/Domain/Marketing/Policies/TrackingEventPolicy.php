<?php

namespace App\Domain\Marketing\Policies;

use App\Domain\Marketing\Models\TrackingEvent;
use App\Models\Admin;

final class TrackingEventPolicy
{
    public function viewAny(Admin $a): bool
    {
        return $a->is_active && $a->hasRole('super-admin');
    }

    public function view(Admin $a, TrackingEvent $r): bool
    {
        return $this->viewAny($a);
    }

    public function create(Admin $a): bool
    {
        return false;
    }

    public function update(Admin $a, TrackingEvent $r): bool
    {
        return false;
    }

    public function delete(Admin $a, TrackingEvent $r): bool
    {
        return false;
    }
}
