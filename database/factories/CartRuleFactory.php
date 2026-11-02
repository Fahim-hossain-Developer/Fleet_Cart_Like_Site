<?php

namespace Database\Factories;

use App\Domain\Marketing\Models\CartRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CartRule> */
class CartRuleFactory extends Factory
{
    protected $model = CartRule::class;

    public function definition(): array
    {
        return ['name' => ['en' => 'Cart saving', 'bn' => 'কার্টে সাশ্রয়'], 'is_active' => true, 'type' => 'percent', 'value' => 10, 'minimum' => 0, 'minimum_qty' => 1];
    }
}
