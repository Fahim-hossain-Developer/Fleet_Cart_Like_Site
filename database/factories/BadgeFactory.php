<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\Badge;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Badge> */
class BadgeFactory extends Factory
{
    protected $model = Badge::class;

    public function definition(): array
    {
        return ['key' => fake()->unique()->slug(), 'label' => ['en' => 'Special', 'bn' => 'বিশেষ'], 'color' => '#065f46', 'is_active' => true];
    }
}
