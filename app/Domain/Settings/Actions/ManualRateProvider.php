<?php

namespace App\Domain\Settings\Actions;

use App\Domain\Settings\Models\Currency;

final class ManualRateProvider implements RateProvider
{
    public function rateFor(string $currency): int
    {
        return Currency::where('code', $currency)->where('is_active', true)->firstOrFail()->rate_ppm;
    }
}
