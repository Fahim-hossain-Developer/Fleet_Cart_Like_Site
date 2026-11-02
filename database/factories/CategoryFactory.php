<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Category> */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        return ['name' => ['en' => 'Category', 'bn' => 'বিভাগ'], 'slug' => fake()->unique()->slug(), 'is_active' => true];
    }
}
