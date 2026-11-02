<?php

namespace App\Jobs;

use App\Domain\Customers\Actions\AdminCode;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class SendAdminCode implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $challengeId) {}

    public function handle(): void
    {
        app(AdminCode::class)->deliver($this->challengeId);
    }
}
