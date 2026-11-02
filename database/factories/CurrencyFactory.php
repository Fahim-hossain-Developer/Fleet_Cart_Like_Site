<?php

namespace Database\Factories;

use App\Domain\Settings\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Currency> */
class CurrencyFactory extends Factory
{
    protected $model = Currency::class;

    public function definition(): array
    {
        do {
            $code = strtoupper(fake()->unique()->lexify('???'));
        } while ($code === 'BDT');

        return ['code' => $code, 'symbol' => '$', 'rate_ppm' => 10000, 'decimals' => 2, 'is_active' => true];
    }
}
