<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\ProductTab;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProductTab> */
class ProductTabFactory extends Factory
{
    protected $model = ProductTab::class;

    public function definition(): array
    {
        return ['product_id' => ProductFactory::new(), 'scope' => 'product', 'title' => ['en' => 'Care', 'bn' => 'যত্ন'], 'body' => ['en' => 'Handle with care.', 'bn' => 'যত্নে ব্যবহার করুন।'], 'is_active' => true];
    }
}
