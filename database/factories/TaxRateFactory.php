<?php

namespace Database\Factories;

use App\Domain\Checkout\Models\TaxRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TaxRate> */
class TaxRateFactory extends Factory
{
    protected $model = TaxRate::class;

    public function definition(): array
    {
        return ['tax_class_id' => TaxClassFactory::new(), 'country' => 'BD', 'rate_bps' => 1000, 'is_active' => true];
    }
}
