<?php

namespace App\Domain\Checkout\Policies;

use App\Domain\Checkout\Models\LandingOrderClaim;
use App\Models\Admin;

final class LandingOrderClaimPolicy
{
    public function viewAny(Admin $a): bool
    {
        return false;
    }

    public function view(Admin $a, LandingOrderClaim $record): bool
    {
        return false;
    }

    public function create(Admin $a): bool
    {
        return false;
    }

    public function update(Admin $a, LandingOrderClaim $record): bool
    {
        return false;
    }

    public function delete(Admin $a, LandingOrderClaim $record): bool
    {
        return false;
    }
}
