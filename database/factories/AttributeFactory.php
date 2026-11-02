<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\Attribute;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Attribute> */
class AttributeFactory extends Factory
{
    protected $model = Attribute::class;

    public function definition(): array
    {
        return ['name' => ['en' => 'Size', 'bn' => 'মাপ'], 'slug' => fake()->unique()->slug(), 'type' => 'text'];
    }
}
