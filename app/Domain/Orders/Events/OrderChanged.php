<?php

namespace App\Domain\Orders\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final class OrderChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public int $orderId, public string $type) {}
}
