<?php

namespace Database\Factories;

use App\Domain\Content\Models\PostCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PostCategory> */
class PostCategoryFactory extends Factory
{
    protected $model = PostCategory::class;

    public function definition(): array
    {
        return ['title' => ['en' => fake()->sentence(3), 'bn' => 'নমুনা শিরোনাম'], 'body' => ['en' => '<p>Original sample content.</p>', 'bn' => '<p>নমুনা বিষয়বস্তু।</p>'], 'status' => 'published', 'published_at' => now()->subMinute(), 'slug' => fake()->unique()->slug(3)];
    }
}
