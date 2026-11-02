<?php

namespace Database\Factories;

use App\Domain\Shipping\Models\ShippingZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ShippingZone> */
class ShippingZoneFactory extends Factory
{
    protected $model = ShippingZone::class;

    public function definition(): array
    {
        return ['name' => ['en' => 'Zone', 'bn' => 'অঞ্চল'], 'country' => 'BD', 'district_ids' => [], 'is_active' => true];
    }
}
