<?php

namespace Database\Factories;

use App\Domain\Shipping\Models\ShippingMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ShippingMethod> */
class ShippingMethodFactory extends Factory
{
    protected $model = ShippingMethod::class;

    public function definition(): array
    {
        return ['shipping_zone_id' => ShippingZoneFactory::new(), 'name' => ['en' => 'Standard', 'bn' => 'সাধারণ'], 'type' => 'flat', 'amount' => 8000, 'is_active' => true];
    }
}
