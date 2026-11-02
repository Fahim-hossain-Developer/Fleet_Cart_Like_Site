<?php

namespace App\Domain\Payments\DTOs;

final readonly class PaymentRedirect
{
    public function __construct(public string $reference, public string $url) {}
}
