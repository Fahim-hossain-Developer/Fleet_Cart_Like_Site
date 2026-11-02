<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\Product;
use App\Domain\Marketing\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Review> */
class ReviewFactory extends Factory
{
    protected $model = Review::class;

    public function definition(): array
    {
        return ['product_id' => Product::factory(), 'user_id' => User::factory(), 'display_name' => 'Customer', 'rating' => 5, 'title' => 'Helpful product', 'body' => 'This is an original review of this product.', 'status' => 'pending'];
    }
}
