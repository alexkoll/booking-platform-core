<?php

namespace App\Booking\Application\Query\GetCurrentUserBookings;

use App\Identity\Domain\ValueObject\UserId;
use App\Provider\Domain\ValueObject\ProviderId;

interface CurrentUserBookingsReader
{
    /**
     * @return string[]
     */
    public function listProviderIdsForUser(UserId $userId): array;

    /**
     * @param array<string, string> $providerLocalDatesById
     *
     * @return array{items: array<int, array<string, mixed>>, filters: array<string, mixed>, pagination: array<string, int>}
     */
    public function listCurrentUserBookings(
        UserId $userId,
        int $page,
        int $limit,
        ?ProviderId $providerId,
        ?string $dateFrom,
        ?string $dateTo,
        ?string $status,
        ?string $period,
        array $providerLocalDatesById
    ): array;
}
