<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return ['name' => ['en' => fake()->words(3, true), 'bn' => 'নমুনা পণ্য'], 'sku' => fake()->unique()->bothify('SKU-########'), 'slug' => fake()->unique()->slug(), 'price' => 125000, 'qty' => 10, 'status' => 'active'];
    }
}
