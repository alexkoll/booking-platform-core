<?php

namespace App\Booking\Application\Query\GetProviderBookingDetails;

use App\Booking\Domain\ValueObject\BookingId;
use App\Identity\Domain\ValueObject\UserId;
use App\Provider\Domain\ValueObject\ProviderId;

final class GetProviderBookingDetailsQuery
{
    public function __construct(
        public readonly UserId $ownerUserId,
        public readonly ProviderId $providerId,
        public readonly BookingId $bookingId,
    ) {
    }
}
