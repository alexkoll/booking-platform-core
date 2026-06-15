<?php

namespace App\Booking\Application\Query\GetProviderBookings;

use App\Identity\Domain\ValueObject\UserId;
use App\Provider\Domain\Entity\Provider;
use App\Provider\Domain\ValueObject\EmployeeId;

interface ProviderBookingsReader
{
    /**
     * @return array{providerType: string, employees: array<int, array<string, mixed>>, items: array<int, array<string, mixed>>, clients: array<int, array<string, mixed>>, pagination: array<string, int>}
     */
    public function listProviderBookings(
        Provider $provider,
        int $page,
        int $limit,
        ?string $dateFrom,
        ?string $dateTo,
        ?string $status,
        ?EmployeeId $employeeId,
        ?UserId $clientId
    ): array;
}
