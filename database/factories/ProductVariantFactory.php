<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProductVariant> */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        return ['product_id' => ProductFactory::new(), 'sku' => fake()->unique()->bothify('VAR-########'), 'attributes' => ['Size' => 'M'], 'price' => 125000, 'qty' => 5, 'is_active' => true];
    }
}
