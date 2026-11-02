<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\AttributeValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AttributeValue> */
class AttributeValueFactory extends Factory
{
    protected $model = AttributeValue::class;

    public function definition(): array
    {
        return ['attribute_id' => AttributeFactory::new(), 'name' => ['en' => 'Medium', 'bn' => 'মাঝারি'], 'value' => fake()->unique()->word()];
    }
}
