<?php

namespace Database\Factories;

use App\Domain\Customers\Models\ShoppingList;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ShoppingList> */
class ShoppingListFactory extends Factory
{
    protected $model = ShoppingList::class;

    public function definition(): array
    {
        return ['owner_key' => 'user:'.fake()->unique()->randomNumber(8), 'user_id' => User::factory()];
    }
}
