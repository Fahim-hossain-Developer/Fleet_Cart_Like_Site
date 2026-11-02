<?php

namespace App\Domain\Payments\DTOs;

final readonly class RefundResult
{
    public function __construct(public RemoteState $state, public string $reference, public int $amount, public string $currency) {}
}
