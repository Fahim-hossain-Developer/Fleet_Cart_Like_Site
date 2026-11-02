<?php

namespace App\Domain\Payments\DTOs;

final readonly class PaymentResult
{
    public function __construct(public RemoteState $state, public string $merchantReference, public string $transactionReference, public int $amount, public string $currency, public bool $merchantVerified, public bool $riskReview = false) {}
}
