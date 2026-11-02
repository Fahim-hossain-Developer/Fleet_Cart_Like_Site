<?php

namespace Database\Factories;

use App\Domain\Orders\Models\Shipment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Shipment> */
class ShipmentFactory extends Factory
{
    protected $model = Shipment::class;

    public function definition(): array
    {
        return ['order_id' => OrderFactory::new(), 'key' => (string) Str::uuid(), 'carrier' => 'Manual carrier', 'tracking_no' => fake()->uuid(), 'items' => []];
    }
}
