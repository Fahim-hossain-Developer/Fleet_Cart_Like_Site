<?php

namespace Database\Factories;

use App\Domain\Checkout\Models\TaxClass;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TaxClass> */
class TaxClassFactory extends Factory
{
    protected $model = TaxClass::class;

    public function definition(): array
    {
        return ['name' => ['en' => 'Standard', 'bn' => 'সাধারণ']];
    }
}
