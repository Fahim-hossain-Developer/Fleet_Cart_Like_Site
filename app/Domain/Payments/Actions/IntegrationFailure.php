<?php

namespace App\Domain\Payments\Actions;

// Never attach a raw HTTP exception: its URL/body may contain merchant credentials.
final class IntegrationFailure extends \RuntimeException
{
    public function __construct(public readonly string $driver, public readonly string $reason)
    {
        parent::__construct($driver.': '.$reason);
    }
}
