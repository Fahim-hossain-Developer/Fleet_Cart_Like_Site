<?php

namespace App\Domain\Settings\Actions;

interface RateProvider
{
    public function rateFor(string $currency): int;
}
