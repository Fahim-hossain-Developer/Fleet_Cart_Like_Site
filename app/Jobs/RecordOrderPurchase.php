<?php

namespace App\Jobs;

use App\Domain\Marketing\Actions\Tracking;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class RecordOrderPurchase implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $orderId) {}

    public function handle(): void
    {
        app(Tracking::class)->purchase($this->orderId);
    }
}
