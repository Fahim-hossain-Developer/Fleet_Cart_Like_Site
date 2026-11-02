<?php

namespace Database\Factories;

use App\Domain\Marketing\Models\Coupon;
use App\Domain\Marketing\Models\SavedCoupon;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SavedCoupon> */
class SavedCouponFactory extends Factory
{
    protected $model = SavedCoupon::class;

    public function definition(): array
    {
        return ['user_id' => User::factory(), 'coupon_id' => Coupon::factory()];
    }
}
