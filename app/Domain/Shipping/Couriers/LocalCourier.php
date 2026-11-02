<?php

namespace App\Domain\Shipping\Couriers;

use App\Domain\Shipping\Contracts\Courier;
use App\Domain\Shipping\DTOs\BookingResult;
use App\Domain\Shipping\Models\CourierBooking;

final class LocalCourier implements Courier
{
    public function book(CourierBooking $booking): BookingResult
    {
        abort_unless($booking->driver === 'courier_fake' && app()->environment('local', 'testing'), 403);

        return new BookingResult('accepted', 'TEST-'.$booking->uuid, 'TEST-'.$booking->id);
    }

    public function query(CourierBooking $booking): BookingResult
    {
        abort_unless($booking->driver === 'courier_fake' && app()->environment('local', 'testing'), 403);

        return new BookingResult($booking->provider_status ?: 'accepted', 'TEST-'.$booking->uuid, 'TEST-'.$booking->id);
    }
}
