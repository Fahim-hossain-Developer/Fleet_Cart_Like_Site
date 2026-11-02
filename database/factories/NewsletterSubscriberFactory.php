<?php

namespace Database\Factories;

use App\Domain\Marketing\Models\NewsletterSubscriber;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<NewsletterSubscriber> */
class NewsletterSubscriberFactory extends Factory
{
    protected $model = NewsletterSubscriber::class;

    public function definition(): array
    {
        $email = fake()->unique()->safeEmail();
        $token = Str::random(64);

        return ['email' => $email, 'email_hash' => hash_hmac('sha256', $email, config('app.key')), 'status' => 'active', 'generation' => 1, 'consented_at' => now(), 'confirmed_at' => now(), 'unsubscribe_token' => $token, 'unsubscribe_hash' => hash('sha256', $token)];
    }
}
