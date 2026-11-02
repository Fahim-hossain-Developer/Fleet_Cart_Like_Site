<?php

namespace App\Domain\Shipping\Couriers;

use App\Domain\Payments\Actions\IntegrationFailure;
use App\Domain\Shipping\Contracts\Courier;
use App\Domain\Shipping\DTOs\BookingResult;
use App\Domain\Shipping\Models\CourierBooking;

// TODO(api-docs): Steadfast, Pathao and RedX require reviewed official merchant protocols.
// Saved private JSON/documentation does not authorize guessed endpoints or webhook signatures.
final class PendingDocumentationCourier implements Courier
{
    public function book(CourierBooking $booking): BookingResult
    {
        throw new IntegrationFailure($booking->driver, 'protocol_pending');
    }

    public function query(CourierBooking $booking): BookingResult
    {
        throw new IntegrationFailure($booking->driver, 'protocol_pending');
    }
}
