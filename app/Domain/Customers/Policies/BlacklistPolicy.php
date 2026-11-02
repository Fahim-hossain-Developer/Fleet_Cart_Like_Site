<?php

namespace App\Domain\Customers\Policies;

use App\Domain\Checkout\Policies\CommercePolicy;

class BlacklistPolicy extends CommercePolicy
{
    protected string $permission = 'customers.manage';
}
