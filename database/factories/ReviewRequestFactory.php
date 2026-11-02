<?php

namespace Database\Factories;

use App\Domain\Marketing\Models\ReviewRequest;
use App\Domain\Orders\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ReviewRequest> */
class ReviewRequestFactory extends Factory
{
    protected $model = ReviewRequest::class;

    public function definition(): array
    {
        return ['order_id' => Order::factory()];
    }
}
