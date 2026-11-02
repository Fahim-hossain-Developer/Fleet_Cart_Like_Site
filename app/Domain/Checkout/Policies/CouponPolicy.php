<?php

namespace App\Domain\Checkout\Policies;

class CouponPolicy extends CommercePolicy
{
    protected string $permission = 'marketing.manage';
}
