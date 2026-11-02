<?php

namespace Database\Factories;

use App\Domain\Content\Models\Popup;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Popup> */
class PopupFactory extends Factory
{
    protected $model = Popup::class;

    public function definition(): array
    {
        return ['title' => ['en' => fake()->sentence(3), 'bn' => 'নমুনা শিরোনাম'], 'body' => ['en' => '<p>Original sample content.</p>', 'bn' => '<p>নমুনা বিষয়বস্তু।</p>'], 'status' => 'published', 'published_at' => now()->subMinute()];
    }
}
