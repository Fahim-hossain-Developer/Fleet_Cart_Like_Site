<?php

namespace Database\Factories;

use App\Domain\Orders\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Order> */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return ['number' => 'TEST-'.Str::uuid(), 'idempotency_key' => (string) Str::uuid(), 'phone' => '+8801712345678', 'currency' => 'BDT', 'display_currency' => 'BDT', 'display_total' => 0, 'shipping_address' => [], 'payment_method' => 'cod', 'shipping_method' => 'Test', 'placed_at' => now()];
    }
}
