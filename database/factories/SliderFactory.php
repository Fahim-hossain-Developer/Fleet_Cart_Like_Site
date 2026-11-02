<?php

namespace Database\Factories;

use App\Domain\Content\Models\Slider;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Slider> */
class SliderFactory extends Factory
{
    protected $model = Slider::class;

    public function definition(): array
    {
        return ['title' => ['en' => fake()->sentence(3), 'bn' => 'নমুনা শিরোনাম'], 'body' => ['en' => '<p>Original sample content.</p>', 'bn' => '<p>নমুনা বিষয়বস্তু।</p>'], 'status' => 'published', 'published_at' => now()->subMinute(), 'key' => strtolower(fake()->unique()->lexify('menu????????'))];
    }
}
