<?php

namespace Database\Factories;

use App\Domain\Marketing\Models\NewsletterMessage;
use App\Domain\Marketing\Models\NewsletterSubscriber;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<NewsletterMessage> */
class NewsletterMessageFactory extends Factory
{
    protected $model = NewsletterMessage::class;

    public function definition(): array
    {
        return ['newsletter_subscriber_id' => NewsletterSubscriber::factory(), 'kind' => 'confirmation', 'generation' => 1, 'payload' => ['token' => Str::random(64)], 'expires_at' => now()->addDay()];
    }
}
