<?php

namespace App\Booking\Application\Service;

use App\Booking\Domain\Repository\BookingRepository;
use App\Booking\Domain\ValueObject\BookingStatus;
use App\Provider\Domain\ValueObject\EmployeeId;
use App\Provider\Domain\ValueObject\ProviderId;
use App\Provider\Domain\ValueObject\ProviderLocationId;

final class BookingOverlapChecker
{
    public function __construct(private readonly BookingRepository $bookings)
    {
    }

    /**
     * @return array<string, true>
     */
    public function occupiedSet(ProviderId $providerId, ProviderLocationId $addressId, string $date, ?EmployeeId $employeeId, int $slotMinutes): array
    {
        $occupiedSet = [];
        foreach ($this->bookings->findByProviderAddressAndDate($providerId, $addressId, $date, $employeeId) as $booking) {
            if (in_array($booking->getStatus(), [
                BookingStatus::CANCELLED,
                BookingStatus::COMPLETED,
                BookingStatus::NO_SHOW,
                BookingStatus::EXPIRED,
            ], true)) {
                continue;
            }
            $existingStart = $this->timeToMinutes($booking->getBookingTime());
            if ($existingStart === null || $booking->getDurationMinutes() <= 0) {
                continue;
            }
            $existingSlots = (int) ceil($booking->getDurationMinutes() / $slotMinutes);
            for ($i = 0; $i < $existingSlots; $i++) {
                $occupiedSet[$this->minutesToTime($existingStart + ($i * $slotMinutes))] = true;
            }
        }

        return $occupiedSet;
    }

    private function timeToMinutes(mixed $time): ?int
    {
        if (!is_string($time) || $time === '') {
            return null;
        }
        $parts = explode(':', $time);
        if (count($parts) < 2) {
            return null;
        }

        return ((int) $parts[0] * 60) + (int) $parts[1];
    }

    private function minutesToTime(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
