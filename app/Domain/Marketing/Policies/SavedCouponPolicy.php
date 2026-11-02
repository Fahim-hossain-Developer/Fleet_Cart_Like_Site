<?php

namespace App\Domain\Marketing\Policies;

use App\Domain\Marketing\Models\SavedCoupon;
use App\Models\User;

final class SavedCouponPolicy
{
    public function view(User $u, SavedCoupon $m): bool
    {
        return ! $u->is_blocked && $m->user_id === $u->id;
    }

    public function update(User $u, SavedCoupon $m): bool
    {
        return $this->view($u, $m);
    }

    public function delete(User $u, SavedCoupon $m): bool
    {
        return $this->view($u, $m);
    }
}
