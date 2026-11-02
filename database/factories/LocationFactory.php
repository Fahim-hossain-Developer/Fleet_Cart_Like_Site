<?php

namespace Database\Factories;

use App\Domain\Shipping\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Location> */
class LocationFactory extends Factory
{
    protected $model = Location::class;

    public function definition(): array
    {
        return ['code' => fake()->unique()->uuid(), 'country' => 'BD', 'type' => 'division', 'name' => ['en' => 'Dhaka', 'bn' => 'ঢাকা']];
    }
}
