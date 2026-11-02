<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\Product;
use App\Domain\Customers\Models\ProductWatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProductWatch> */
class ProductWatchFactory extends Factory
{
    protected $model = ProductWatch::class;

    public function definition(): array
    {
        return ['user_id' => User::factory(), 'product_id' => Product::factory(), 'kind' => 'stock', 'last_price' => 100000, 'last_available' => false];
    }
}
