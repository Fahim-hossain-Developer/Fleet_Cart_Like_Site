<?php

namespace Database\Factories;

use App\Domain\Customers\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<NotificationPreference> */
class NotificationPreferenceFactory extends Factory
{
    protected $model = NotificationPreference::class;

    public function definition(): array
    {
        return ['user_id' => User::factory(), 'topic' => 'orders', 'channel' => 'in_app', 'is_enabled' => true, 'consented_at' => now()];
    }
}
