<?php

namespace Database\Factories;

use App\Domain\Marketing\Models\NewsletterCampaign;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<NewsletterCampaign> */
class NewsletterCampaignFactory extends Factory
{
    protected $model = NewsletterCampaign::class;

    public function definition(): array
    {
        return ['name' => ['en' => 'Store journal', 'bn' => 'দোকানের খবর'], 'body' => ['en' => 'Original store news.', 'bn' => 'দোকানের নতুন খবর।']];
    }
}
