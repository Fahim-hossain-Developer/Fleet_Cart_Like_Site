<?php

namespace App\Domain\Shipping\Contracts;

use App\Domain\Shipping\DTOs\BookingResult;
use App\Domain\Shipping\Models\CourierBooking;

interface Courier
{
    public function book(CourierBooking $booking): BookingResult;

    public function query(CourierBooking $booking): BookingResult;
}
