<?php

namespace App\Booking\Application\Query\GetProviderBookings;

use App\Identity\Domain\ValueObject\UserId;
use App\Provider\Domain\ValueObject\EmployeeId;
use App\Provider\Domain\ValueObject\ProviderId;

final class GetProviderBookingsQuery
{
    public function __construct(
        public readonly UserId $ownerUserId,
        public readonly int $page,
        public readonly int $limit,
        public readonly ?string $dateFrom,
        public readonly ?string $dateTo,
        public readonly ?string $status,
        public readonly ?EmployeeId $employeeId,
        public readonly ?UserId $clientId,
        public readonly ?string $period,
    ) {
    }
}
