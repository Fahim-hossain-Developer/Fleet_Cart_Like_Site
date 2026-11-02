<?php

namespace Database\Factories;

use App\Domain\Orders\Models\OrderReturn;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OrderReturn> */
class OrderReturnFactory extends Factory
{
    protected $model = OrderReturn::class;

    public function definition(): array
    {
        return ['order_id' => OrderFactory::new(), 'reason' => fake()->sentence(), 'items' => [], 'status' => 'requested'];
    }
}
