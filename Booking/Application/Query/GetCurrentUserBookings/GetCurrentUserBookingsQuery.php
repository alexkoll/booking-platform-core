<?php

namespace App\Booking\Application\Query\GetCurrentUserBookings;

use App\Identity\Domain\ValueObject\UserId;
use App\Provider\Domain\ValueObject\ProviderId;

final class GetCurrentUserBookingsQuery
{
    public function __construct(
        public readonly UserId $userId,
        public readonly int $page,
        public readonly int $limit,
        public readonly ?ProviderId $providerId,
        public readonly ?string $dateFrom,
        public readonly ?string $dateTo,
        public readonly ?string $status,
        public readonly ?string $period,
    ) {
    }
}
