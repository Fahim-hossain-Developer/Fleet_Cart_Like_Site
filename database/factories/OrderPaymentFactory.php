<?php

namespace Database\Factories;

use App\Domain\Orders\Models\OrderPayment;
use App\Models\Admin;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<OrderPayment> */
class OrderPaymentFactory extends Factory
{
    protected $model = OrderPayment::class;

    public function definition(): array
    {
        return ['order_id' => OrderFactory::new(), 'admin_id' => Admin::query()->create(['name' => 'Test staff', 'email' => fake()->unique()->safeEmail(), 'password' => Str::random(32)])->id, 'key' => (string) Str::uuid(), 'amount' => 10000, 'reference' => fake()->uuid()];
    }
}
