<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Tag> */
class TagFactory extends Factory
{
    protected $model = Tag::class;

    public function definition(): array
    {
        return ['name' => ['en' => 'Tag', 'bn' => 'ট্যাগ'], 'slug' => fake()->unique()->slug()];
    }
}
