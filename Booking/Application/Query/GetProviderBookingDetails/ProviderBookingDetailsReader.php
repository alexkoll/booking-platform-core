<?php

namespace App\Booking\Application\Query\GetProviderBookingDetails;

use App\Booking\Domain\ValueObject\BookingId;
use App\Provider\Domain\Entity\Provider;

interface ProviderBookingDetailsReader
{
    /**
     * @return array{booking: array<string, mixed>}|null
     */
    public function getProviderBookingDetails(Provider $provider, BookingId $bookingId): ?array;
}
