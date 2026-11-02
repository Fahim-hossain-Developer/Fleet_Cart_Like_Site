<?php

namespace Database\Factories;

use App\Domain\Marketing\Models\FlashSale;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FlashSale> */
class FlashSaleFactory extends Factory
{
    protected $model = FlashSale::class;

    public function definition(): array
    {
        return ['name' => ['en' => 'Limited selection', 'bn' => 'সীমিত অফার'], 'is_active' => true, 'starts_at' => now()->subMinute(), 'ends_at' => now()->addDay()];
    }
}
