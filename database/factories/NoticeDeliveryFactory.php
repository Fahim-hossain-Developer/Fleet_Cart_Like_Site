<?php

namespace Database\Factories;

use App\Domain\Customers\Models\NoticeDelivery;
use App\Domain\Customers\Models\StoreNotification;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<NoticeDelivery> */
class NoticeDeliveryFactory extends Factory
{
    protected $model = NoticeDelivery::class;

    public function definition(): array
    {
        return ['notification_id' => StoreNotification::factory(), 'channel' => 'email'];
    }
}
