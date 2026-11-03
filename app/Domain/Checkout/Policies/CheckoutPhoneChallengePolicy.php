<?php

namespace App\Domain\Checkout\Policies;

use App\Domain\Checkout\Models\CheckoutPhoneChallenge;
use App\Models\Admin;

final class CheckoutPhoneChallengePolicy
{
    public function viewAny(Admin $a): bool
    {
        return false;
    }

    public function view(Admin $a, CheckoutPhoneChallenge $record): bool
    {
        return false;
    }

    public function create(Admin $a): bool
    {
        return false;
    }

    public function update(Admin $a, CheckoutPhoneChallenge $record): bool
    {
        return false;
    }

    public function delete(Admin $a, CheckoutPhoneChallenge $record): bool
    {
        return false;
    }
}
