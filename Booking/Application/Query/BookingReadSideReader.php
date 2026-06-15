<?php

namespace App\Booking\Application\Query;

use App\Identity\Domain\ValueObject\UserId;
use App\Provider\Domain\Entity\Provider;
use App\Provider\Domain\ValueObject\EmployeeId;
use App\Provider\Domain\ValueObject\ProviderId;

interface BookingReadSideReader
{
    /**
     * TODO: Replace this compatibility response with the paginated `items` contract once old consumers are removed.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listBookingsByUserForApi(UserId $userId): array;

    /**
     * TODO: Replace this compatibility response with the paginated `items` contract once old consumers are removed.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listBookingsByProviderForApi(ProviderId $providerId): array;

    /**
     * @return array{total: int, completed: int, no_show: int, cancelled: int, confirmed: int, pending: int}
     */
    public function statsForProviderAndUser(ProviderId $providerId, UserId $userId): array;

    public function countCompletedForProvider(ProviderId $providerId): int;

    /**
     * @return array{bookingsCount: int, bookingsPreview: array<int, array<string, mixed>>}
     */
    public function userProfileBookingSummary(UserId $userId): array;

    /**
     * @return array{completedOrders: int, bookingsPreview: array<int, array<string, mixed>>}
     */
    public function providerProfileBookingSummary(ProviderId $providerId): array;

    /**
     * @return array<int, array{id: string, status: string, addressId: string, employeeId: ?string, date: string, time: string, durationMinutes: int}>
     */
    public function providerBookingSlotSources(ProviderId $providerId): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function providerCalendarBookings(Provider $provider, string $from, string $to, ?EmployeeId $employeeId): array;

    /**
     * @return array{items: array<int, array<string, mixed>>, clients: array<int, array<string, mixed>>, pagination: array<string, int>}
     */
    public function listEmployeeBookings(
        Provider $provider,
        EmployeeId $employeeId,
        int $page,
        int $limit,
        ?string $dateFrom,
        ?string $dateTo,
        ?string $status,
        ?UserId $clientId
    ): array;
}
