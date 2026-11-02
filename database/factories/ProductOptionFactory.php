<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\ProductOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProductOption> */
class ProductOptionFactory extends Factory
{
    protected $model = ProductOption::class;

    public function definition(): array
    {
        return ['product_id' => ProductFactory::new(), 'label' => ['en' => 'Engraving', 'bn' => 'খোদাই'], 'type' => 'text', 'price' => 1000];
    }
}
