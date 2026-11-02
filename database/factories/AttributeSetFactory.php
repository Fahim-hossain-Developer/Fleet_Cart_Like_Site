<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\AttributeSet;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AttributeSet> */
class AttributeSetFactory extends Factory
{
    protected $model = AttributeSet::class;

    public function definition(): array
    {
        return ['name' => ['en' => 'Apparel', 'bn' => 'পোশাক'], 'slug' => fake()->unique()->slug()];
    }
}
