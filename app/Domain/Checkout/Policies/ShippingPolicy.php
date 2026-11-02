<?php

namespace App\Domain\Checkout\Policies;

class ShippingPolicy extends CommercePolicy
{
    protected string $permission = 'shipping.manage';
}
