<?php

namespace Database\Factories;

use App\Domain\Customers\Models\BlacklistedPhone;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BlacklistedPhone> */
class BlacklistedPhoneFactory extends Factory
{
    protected $model = BlacklistedPhone::class;

    public function definition(): array
    {
        return ['phone' => '+88017'.fake()->unique()->numerify('########'), 'reason' => fake()->sentence()];
    }
}
