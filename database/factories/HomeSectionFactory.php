<?php

namespace Database\Factories;

use App\Domain\Content\Models\HomeSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<HomeSection> */
class HomeSectionFactory extends Factory
{
    protected $model = HomeSection::class;

    public function definition(): array
    {
        return ['title' => ['en' => fake()->sentence(3), 'bn' => 'নমুনা শিরোনাম'], 'body' => ['en' => '<p>Original sample content.</p>', 'bn' => '<p>নমুনা বিষয়বস্তু।</p>'], 'status' => 'published', 'published_at' => now()->subMinute(), 'type' => 'rich_text', 'config' => []];
    }
}
