<?php

namespace App\Booking\Domain\Repository;

use App\Booking\Domain\Entity\Booking;
use App\Booking\Domain\ValueObject\BookingId;
use App\Provider\Domain\ValueObject\EmployeeId;
use App\Provider\Domain\ValueObject\ProviderId;

interface BookingRepository
{
    public function add(Booking $booking): void;

    public function byId(BookingId $id): Booking;

    public function hasBookingsForEmployee(EmployeeId $employeeId): bool;

    /**
     * @return Booking[]
     */
    public function findExpiredPaymentBookings(\DateTimeImmutable $now, int $limit = 200): array;

    /**
     * @return Booking[]
     */
    public function findByProviderAddressAndDate(ProviderId $providerId, \App\Provider\Domain\ValueObject\ProviderLocationId $addressId, string $date, ?EmployeeId $employeeId = null): array;
}
