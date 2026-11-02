<?php

namespace Database\Factories;

use App\Domain\Orders\Models\OrderRefund;
use App\Models\Admin;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<OrderRefund> */
class OrderRefundFactory extends Factory
{
    protected $model = OrderRefund::class;

    public function definition(): array
    {
        return ['order_id' => OrderFactory::new(), 'admin_id' => Admin::query()->create(['name' => 'Test staff', 'email' => fake()->unique()->safeEmail(), 'password' => Str::random(32)])->id, 'key' => (string) Str::uuid(), 'amount' => 10000, 'reason' => fake()->sentence(), 'items' => []];
    }
}
