<?php

namespace App\Domain\Shipping\DTOs;

final readonly class BookingResult
{
    public function __construct(public string $status, public ?string $reference = null, public ?string $tracking = null, public ?int $collected = null, public ?int $fee = null) {}
}
