<?php

namespace Database\Factories;

use App\Domain\Marketing\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Coupon> */
class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    public function definition(): array
    {
        return ['code' => strtoupper(fake()->unique()->bothify('TEST-#####')), 'type' => 'fixed', 'value' => 5000, 'is_active' => true];
    }
}
