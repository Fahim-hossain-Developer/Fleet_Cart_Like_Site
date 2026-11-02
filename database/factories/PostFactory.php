<?php

namespace Database\Factories;

use App\Domain\Content\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Post> */
class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition(): array
    {
        return ['title' => ['en' => fake()->sentence(3), 'bn' => 'নমুনা শিরোনাম'], 'body' => ['en' => '<p>Original sample content.</p>', 'bn' => '<p>নমুনা বিষয়বস্তু।</p>'], 'status' => 'published', 'published_at' => now()->subMinute(), 'slug' => fake()->unique()->slug(3)];
    }
}
