<?php

namespace App\Booking\Application\Command\CancelBookingByClient;

final class CancelBookingByClientCommand
{
    public function __construct(
        public readonly string $bookingId,
        public readonly string $clientUserId
    ) {
    }
}
