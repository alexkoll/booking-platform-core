<?php

namespace App\Booking\Infrastructure\ReadModel;

use App\Booking\Application\Query\BookingReadSideReader;
use App\Identity\Domain\ValueObject\UserId;
use App\Provider\Domain\Entity\Provider;
use App\Provider\Domain\ValueObject\EmployeeId;
use App\Provider\Domain\ValueObject\ProviderId;

final class DoctrineBookingReadSideReader implements BookingReadSideReader
{
    public function __construct(
        private readonly DoctrineBookingCompatibilityReader $compatibility,
        private readonly DoctrineBookingSummaryReader $summary,
        private readonly DoctrineBookingCalendarReader $calendar,
        private readonly DoctrineProviderBookingsReader $providerBookings,
    ) {
    }

    public function listBookingsByUserForApi(UserId $userId): array
    {
        return $this->compatibility->listBookingsByUserForApi($userId);
    }

    public function listBookingsByProviderForApi(ProviderId $providerId): array
    {
        return $this->compatibility->listBookingsByProviderForApi($providerId);
    }

    public function statsForProviderAndUser(ProviderId $providerId, UserId $userId): array
    {
        return $this->summary->statsForProviderAndUser($providerId, $userId);
    }

    public function countCompletedForProvider(ProviderId $providerId): int
    {
        return $this->summary->countCompletedForProvider($providerId);
    }

    public function userProfileBookingSummary(UserId $userId): array
    {
        return $this->summary->userProfileBookingSummary($userId);
    }

    public function providerProfileBookingSummary(ProviderId $providerId): array
    {
        return $this->summary->providerProfileBookingSummary($providerId);
    }

    public function providerBookingSlotSources(ProviderId $providerId): array
    {
        return $this->calendar->providerBookingSlotSources($providerId);
    }

    public function providerCalendarBookings(Provider $provider, string $from, string $to, ?EmployeeId $employeeId): array
    {
        return $this->calendar->providerCalendarBookings($provider, $from, $to, $employeeId);
    }

    public function listEmployeeBookings(
        Provider $provider,
        EmployeeId $employeeId,
        int $page,
        int $limit,
        ?string $dateFrom,
        ?string $dateTo,
        ?string $status,
        ?UserId $clientId
    ): array {
        return $this->providerBookings->listEmployeeBookings($provider, $employeeId, $page, $limit, $dateFrom, $dateTo, $status, $clientId);
    }
}
