<?php

namespace App\Booking\Application\Service;

use App\Booking\Domain\Exception\BookingSlotBlockedException;
use App\Booking\Domain\Repository\BookingRepository;
use App\Booking\Domain\ValueObject\BookingStatus;
use App\I18n\Application\Service\TimezoneCatalog;
use App\Provider\Application\Service\ProviderScheduleOverrideResolver;
use App\Provider\Domain\Entity\Provider;
use App\Provider\Domain\Repository\EmployeeScheduleRepository;
use App\Provider\Domain\Repository\ProviderScheduleRepository;
use App\Provider\Domain\ValueObject\EmployeeId;
use App\Provider\Domain\ValueObject\ProviderId;
use App\Provider\Domain\ValueObject\ProviderLocationId;
use RuntimeException;
use Symfony\Contracts\Translation\TranslatorInterface;

final class BookingAvailabilityChecker
{
    public function __construct(
        private readonly BookingRepository $bookings,
        private readonly ProviderScheduleRepository $schedules,
        private readonly EmployeeScheduleRepository $employeeSchedules,
        private readonly TranslatorInterface $translator,
        private readonly TimezoneCatalog $timezoneCatalog,
        private readonly ProviderScheduleOverrideResolver $scheduleOverrides,
        private readonly BookingScheduleAvailability $scheduleAvailability,
        private readonly BookingSlotBlockChecker $slotBlocks,
        private readonly BookingOverlapChecker $overlaps,
    ) {
    }

    public function assertOnlineNotPast(Provider $provider, string $date, string $time): void
    {
        $timezoneName = trim((string) $provider->getTimezone());
        if (!$this->timezoneCatalog->isSupportedTimezone($timezoneName)) {
            $timezoneName = $this->timezoneCatalog->defaultTimezone();
        }

        $timezone = new \DateTimeZone($timezoneName);
        $slotStart = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $date . ' ' . $time, $timezone);
        if (!$slotStart) {
            throw new RuntimeException($this->translator->trans('booking.slot_unavailable'));
        }

        if ($slotStart <= new \DateTimeImmutable('now', $timezone)) {
            throw new RuntimeException($this->translator->trans('booking.slot_unavailable'));
        }
    }

    public function assertOnlineAvailable(
        ProviderId $providerId,
        ProviderLocationId $addressId,
        string $date,
        string $time,
        int $durationMinutes,
        ?EmployeeId $employeeId = null
    ): void {
        if ($durationMinutes <= 0) {
            throw new RuntimeException('Service duration is required');
        }

        $dayOfWeek = $this->dayOfWeekFromDate($date);
        $providerScheduleItems = array_filter(
            $this->schedules->byProviderId($providerId),
            static fn($item) => $item->isActive()
                && (string) $item->getLocationId() === (string) $addressId
                && $item->getDayOfWeek() === $dayOfWeek
        );

        $scheduleItems = $providerScheduleItems;
        if ($employeeId) {
            $employeeScheduleItems = array_filter(
                $this->employeeSchedules->byEmployeeId($employeeId),
                static fn($item) => $item->isActive()
                    && (string) $item->getLocationId() === (string) $addressId
                    && $item->getDayOfWeek() === $dayOfWeek
            );
            if ($employeeScheduleItems) {
                $scheduleItems = $employeeScheduleItems;
            }
        }

        $dateObj = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$dateObj) {
            throw new RuntimeException($this->translator->trans('booking.slot_unavailable'));
        }
        $scheduleRows = $this->scheduleOverrides->applyOverride(
            $dateObj,
            $this->normalizeScheduleRows($scheduleItems),
            $this->scheduleOverrides->findOverride((string) $providerId, (string) $addressId, $employeeId ? (string) $employeeId : null, $dateObj),
        );

        $fit = $this->scheduleAvailability->assertFits($scheduleRows, $time, $durationMinutes, $this->translator);
        $slotMinutes = $fit['slotMinutes'];
        $startMinutes = $fit['startMinutes'];
        $allowedSet = $fit['allowedSet'];
        $blockedSet = $this->slotBlocks->blockedSet($providerId, $addressId, $date, $employeeId);
        $occupiedSet = $this->overlaps->occupiedSet($providerId, $addressId, $date, $employeeId, $slotMinutes);

        $requiredSlots = (int) ceil($durationMinutes / $slotMinutes);
        for ($i = 0; $i < $requiredSlots; $i++) {
            $slotTime = $this->minutesToTime($startMinutes + ($i * $slotMinutes));
            if (isset($blockedSet[$slotTime])) {
                throw new BookingSlotBlockedException($this->translator->trans('booking.slot_blocked'));
            }
            if (!isset($allowedSet[$slotTime]) || isset($occupiedSet[$slotTime])) {
                throw new RuntimeException($this->translator->trans('booking.slot_unavailable'));
            }
        }
    }

    public function resolveOfflineSlotMinutes(ProviderId $providerId, ProviderLocationId $addressId, string $date, ?EmployeeId $employeeId = null): int
    {
        $dayOfWeek = (int) \DateTimeImmutable::createFromFormat('Y-m-d', $date)->format('N');

        if ($employeeId) {
            foreach ($this->employeeSchedules->byEmployeeId($employeeId) as $item) {
                if ((string) $item->getLocationId() === (string) $addressId && $item->isActive() && $item->getDayOfWeek() === $dayOfWeek) {
                    return $item->getSlotMinutes();
                }
            }
        } else {
            foreach ($this->schedules->byProviderId($providerId) as $item) {
                if ((string) $item->getLocationId() === (string) $addressId && $item->isActive() && $item->getDayOfWeek() === $dayOfWeek) {
                    return $item->getSlotMinutes();
                }
            }
        }

        return 0;
    }

    public function assertOfflineAvailable(
        ProviderId $providerId,
        ProviderLocationId $addressId,
        string $date,
        string $time,
        int $durationMinutes,
        ?EmployeeId $employeeId
    ): void {
        $dayOfWeek = (int) \DateTimeImmutable::createFromFormat('Y-m-d', $date)->format('N');
        $scheduleItems = $employeeId
            ? array_filter(
                $this->employeeSchedules->byEmployeeId($employeeId),
                static fn($item) => $item->isActive()
                    && (string) $item->getLocationId() === (string) $addressId
                    && $item->getDayOfWeek() === $dayOfWeek
            )
            : array_filter(
                $this->schedules->byProviderId($providerId),
                static fn($item) => $item->isActive()
                    && (string) $item->getLocationId() === (string) $addressId
                    && $item->getDayOfWeek() === $dayOfWeek
            );
        $daySchedule = reset($scheduleItems) ?: null;
        if (!$daySchedule) {
            throw new RuntimeException('Slot unavailable 1');
        }

        $start = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $date . ' ' . $time)
            ?: \DateTimeImmutable::createFromFormat('Y-m-d H:i', $date . ' ' . $time);
        if (!$start) {
            throw new RuntimeException('Slot unavailable');
        }
        $end = $start->modify('+' . $durationMinutes . ' minutes');

        $workFromStr = substr($daySchedule->getTimeFrom(), 0, 5);
        $workToStr = substr($daySchedule->getTimeTo(), 0, 5);
        $workStart = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $date . ' ' . $workFromStr);
        $workEnd = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $date . ' ' . $workToStr);
        if ($start < $workStart || $end > $workEnd) {
            throw new RuntimeException('Slot too short');
        }

        foreach ($this->bookings->findByProviderAddressAndDate($providerId, $addressId, $date, $employeeId) as $booking) {
            if (!in_array($booking->getStatus(), [BookingStatus::PENDING, BookingStatus::CONFIRMED], true)) {
                continue;
            }
            $bookingStart = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $date . ' ' . $booking->getBookingTime())
                ?: \DateTimeImmutable::createFromFormat('Y-m-d H:i', $date . ' ' . $booking->getBookingTime());
            if (!$bookingStart) {
                continue;
            }
            $bookingEnd = $bookingStart->modify('+' . $booking->getDurationMinutes() . ' minutes');
            if ($start < $bookingEnd && $end > $bookingStart) {
                throw new RuntimeException('Slot unavailable ' . $booking->getId());
            }
        }
    }

    private function dayOfWeekFromDate(string $date): int
    {
        $dateObj = \DateTimeImmutable::createFromFormat('Y-m-d', $date);
        if (!$dateObj) {
            throw new RuntimeException('Invalid date format');
        }

        return (int) $dateObj->format('N');
    }

    private function normalizeScheduleRows(array $scheduleItems): array
    {
        return array_map(static fn($item): array => [
            'dayOfWeek' => $item->getDayOfWeek(),
            'timeFrom' => $item->getTimeFrom(),
            'timeTo' => $item->getTimeTo(),
            'breakFrom' => $item->getBreakFrom(),
            'breakTo' => $item->getBreakTo(),
            'slotMinutes' => $item->getSlotMinutes(),
        ], $scheduleItems);
    }

    private function timeToMinutes(string $time): ?int
    {
        $parts = explode(':', $time);
        if (count($parts) < 2) {
            return null;
        }
        $hours = (int) $parts[0];
        $minutes = (int) $parts[1];
        if ($hours < 0 || $minutes < 0 || $minutes > 59) {
            return null;
        }

        return ($hours * 60) + $minutes;
    }

    private function minutesToTime(int $totalMinutes): string
    {
        $hours = intdiv($totalMinutes, 60);
        $minutes = $totalMinutes % 60;

        return str_pad((string) $hours, 2, '0', STR_PAD_LEFT)
            . ':' . str_pad((string) $minutes, 2, '0', STR_PAD_LEFT);
    }
}
