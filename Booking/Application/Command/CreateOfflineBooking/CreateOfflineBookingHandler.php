<?php

namespace App\Booking\Application\Command\CreateOfflineBooking;

use App\Booking\Application\Event\BookingCreated;
use App\Booking\Application\Service\BookingAvailabilityChecker;
use App\Booking\Application\Service\BookingCreationContextResolver;
use App\Booking\Application\Service\BookingServiceSnapshotBuilder;
use App\Booking\Domain\Entity\Booking;
use App\Booking\Domain\Exception\BookingSlotUnavailableException;
use App\Booking\Domain\Repository\BookingRepository;
use App\Booking\Domain\ValueObject\BookingSource;
use App\Booking\Domain\ValueObject\BookingStatus;
use App\Provider\Domain\ValueObject\EmployeeId;
use App\Provider\Domain\ValueObject\ProviderId;
use App\Provider\Domain\ValueObject\ProviderLocationId;
use RuntimeException;
use Symfony\Component\Messenger\MessageBusInterface;

final class CreateOfflineBookingHandler
{
    public function __construct(
        private readonly BookingRepository $bookings,
        private readonly BookingCreationContextResolver $contextResolver,
        private readonly BookingServiceSnapshotBuilder $snapshotBuilder,
        private readonly BookingAvailabilityChecker $availability,
        private readonly MessageBusInterface $bus
    ) {
    }

    public function __invoke(CreateOfflineBookingCommand $command): Booking
    {
        $providerId = ProviderId::fromString($command->providerId);
        $addressId = ProviderLocationId::fromString($command->addressId);
        [$date, $time] = $this->parseDateAndTime($command);
        $employeeId = $command->employeeId ? EmployeeId::fromString($command->employeeId) : null;

        $context = $this->contextResolver->resolveOffline($providerId, $addressId, $employeeId);
        $snapshot = $this->snapshotBuilder->buildOffline($context->provider, $addressId, $command->serviceIds, $employeeId);

        $slots = array_values(array_filter($command->slots, static fn($value) => is_string($value) && $value !== ''));
        $time = $slots ? min($slots) : $time;
        $slotMinutes = $this->availability->resolveOfflineSlotMinutes($providerId, $addressId, $date, $employeeId);
        $durationMinutes = $slots ? count($slots) * $slotMinutes : $this->snapshotBuilder->calculateOfflineDurationMinutes($snapshot);

        $servicesTotalPrice = $this->snapshotBuilder->calculateServicesTotalPrice($snapshot);
        $customTotalPrice = null;
        if ($command->totalPrice !== null && is_numeric($command->totalPrice)) {
            $normalizedRequestedTotal = (float) $command->totalPrice;
            if (abs($normalizedRequestedTotal - $servicesTotalPrice) >= 0.01) {
                $customTotalPrice = $normalizedRequestedTotal;
            }
        }

        $this->availability->assertOfflineAvailable($providerId, $addressId, $date, $time, $durationMinutes, $employeeId);

        $booking = Booking::create(
            $providerId,
            null,
            $addressId,
            $snapshot,
            $date,
            $time,
            $durationMinutes,
            BookingStatus::CONFIRMED,
            $employeeId,
            false,
            'none',
            null,
            null,
            null,
            $customTotalPrice,
            null,
            Booking::PAYMENT_STATUS_NONE,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            $command->clientName,
            $command->additionalInfo,
            BookingSource::OFFLINE
        );

        try {
            $this->bookings->add($booking);
        } catch (BookingSlotUnavailableException $exception) {
            throw new RuntimeException('Slot unavailable', 0, $exception);
        }

        $this->bus->dispatch(new BookingCreated(
            (string) $booking->getId(),
            (string) $booking->getProviderId(),
            null,
            (string) $booking->getAddressId(),
            $booking->getBookingDate(),
            $booking->getBookingTime(),
            $booking->getDurationMinutes(),
            $booking->getStatus(),
            $booking->getClientName(),
            $booking->getAdditionalInfo(),
            $booking->getSource()
        ));

        return $booking;
    }

    /**
     * @return array{string, string}
     */
    private function parseDateAndTime(CreateOfflineBookingCommand $command): array
    {
        $dateObj = \DateTimeImmutable::createFromFormat('Y-m-d', $command->date);
        if (!$dateObj) {
            throw new RuntimeException('Invalid date');
        }
        $timeObj = \DateTimeImmutable::createFromFormat('H:i', $command->time)
            ?: \DateTimeImmutable::createFromFormat('H:i:s', $command->time);
        if (!$timeObj) {
            throw new RuntimeException('Invalid time');
        }

        return [$dateObj->format('Y-m-d'), $timeObj->format('H:i:s')];
    }
}
