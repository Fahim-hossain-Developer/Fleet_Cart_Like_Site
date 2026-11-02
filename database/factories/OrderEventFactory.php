<?php

namespace Database\Factories;

use App\Domain\Orders\Models\OrderEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OrderEvent> */
class OrderEventFactory extends Factory
{
    protected $model = OrderEvent::class;

    public function definition(): array
    {
        return ['order_id' => OrderFactory::new(), 'type' => 'note', 'note' => fake()->sentence(), 'is_public' => false];
    }
}
