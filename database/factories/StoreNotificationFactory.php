<?php

namespace Database\Factories;

use App\Domain\Customers\Models\StoreNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<StoreNotification> */
class StoreNotificationFactory extends Factory
{
    protected $model = StoreNotification::class;

    public function definition(): array
    {
        return ['id' => (string) Str::uuid(), 'type' => 'store', 'notifiable_type' => User::class, 'notifiable_id' => User::factory(), 'data' => ['key' => 'marketing.notice_order', 'params' => [], 'url' => '/account', 'in_app' => true], 'dedupe_key' => hash('sha256', fake()->uuid())];
    }
}
