<?php

namespace App\Jobs;

use App\Domain\Customers\Actions\Sms;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class SendSms implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $deliveryId) {}

    public function handle(): void
    {
        app(Sms::class)->deliver($this->deliveryId);
    }
}
