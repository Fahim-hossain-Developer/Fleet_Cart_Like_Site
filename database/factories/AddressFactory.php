<?php

namespace Database\Factories;

use App\Domain\Customers\Models\Address;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Address> */
class AddressFactory extends Factory
{
    protected $model = Address::class;

    public function definition(): array
    {
        return ['user_id' => UserFactory::new(), 'name' => fake()->name(), 'phone' => '+8801712345678', 'country' => 'BD', 'division_id' => LocationFactory::new()->state(['type' => 'division']), 'district_id' => function (array $attributes) {
            return LocationFactory::new()->create(['type' => 'district', 'parent_id' => $attributes['division_id']])->id;
        }, 'upazila_id' => function (array $attributes) {
            return LocationFactory::new()->create(['type' => 'upazila', 'parent_id' => $attributes['district_id']])->id;
        }, 'line1' => fake()->streetAddress(), 'is_default' => false];
    }
}
