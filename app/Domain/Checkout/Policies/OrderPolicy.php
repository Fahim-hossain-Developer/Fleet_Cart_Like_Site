<?php

namespace App\Domain\Checkout\Policies;

class OrderPolicy extends CommercePolicy
{
    protected string $permission = 'orders.manage';
}
