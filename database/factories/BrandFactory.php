<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\Brand;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Brand> */
class BrandFactory extends Factory
{
    protected $model = Brand::class;

    public function definition(): array
    {
        return ['name' => ['en' => 'Brand', 'bn' => 'ব্র্যান্ড'], 'slug' => fake()->unique()->slug(), 'is_active' => true];
    }
}
