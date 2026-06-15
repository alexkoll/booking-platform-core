<?php

namespace App\Booking\Application\Command\CancelBookingByClient;

use App\Booking\Application\Event\BookingStatusChanged;
use App\Booking\Domain\Entity\Booking;
use App\Booking\Domain\Repository\BookingRepository;
use App\Booking\Domain\ValueObject\BookingId;
use App\Booking\Domain\ValueObject\BookingStatus;
use App\Identity\Domain\ValueObject\UserId;
use App\Payment\Application\Service\RefundPaymentService;
use App\Provider\Domain\Repository\ProviderRepository;
use App\Provider\Domain\ValueObject\ProviderId;
use App\Provider\Infrastructure\Cache\PublicProviderPageBootstrapCacheInvalidator;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Symfony\Component\Messenger\MessageBusInterface;

final class CancelBookingByClientHandler
{
    private const CANCELLATION_DEADLINE_HOURS = 2;

    public function __construct(
        private readonly BookingRepository $bookings,
        private readonly ProviderRepository $providers,
        private readonly RefundPaymentService $refundPayment,
        private readonly MessageBusInterface $bus,
        private readonly PublicProviderPageBootstrapCacheInvalidator $publicPageCache
    ) {
    }

    /**
     * @return array{status: string, cancelledBy: string, cancelReason: string, cancelledAt: string}
     */
    public function __invoke(CancelBookingByClientCommand $command): array
    {
        $booking = $this->bookings->byId(BookingId::fromString($command->bookingId));
        $clientUserId = UserId::fromString($command->clientUserId);
        if (!$booking->getUserId() || (string) $booking->getUserId() !== (string) $clientUserId) {
            throw new RuntimeException('Booking not found');
        }

        if (!in_array($booking->getStatus(), [BookingStatus::PENDING, BookingStatus::CONFIRMED], true)) {
            throw new RuntimeException('Booking cannot be cancelled in its current status');
        }

        $provider = $this->providers->byId(ProviderId::fromString((string) $booking->getProviderId()));
        $timezone = $this->resolveProviderTimezone($provider?->getTimezone());
        $providerNow = new DateTimeImmutable('now', $timezone);
        $startsAt = $booking->getStartsAtIn($timezone);

        if ($startsAt <= $providerNow->modify('+' . self::CANCELLATION_DEADLINE_HOURS . ' hours')) {
            throw new RuntimeException('Booking can only be cancelled at least 2 hours before appointment start');
        }

        $oldStatus = $booking->getStatus();
        $cancelledAt = new DateTimeImmutable();
        $booking->cancel(
            Booking::CANCELLED_BY_CLIENT,
            Booking::CANCEL_REASON_CLIENT_BEFORE_DEADLINE,
            $cancelledAt
        );
        $this->bookings->add($booking);
        $this->publicPageCache->invalidateProvider($booking->getProviderId());

        if ($booking->getPaymentStatus() === Booking::PAYMENT_STATUS_PAID) {
            $this->refundPayment->refundByBookingId($booking->getId(), Booking::CANCEL_REASON_CLIENT_BEFORE_DEADLINE);
        }

        if ($oldStatus !== $booking->getStatus()) {
            $this->bus->dispatch(new BookingStatusChanged(
                (string) $booking->getId(),
                (string) $booking->getProviderId(),
                (string) $booking->getUserId(),
                $oldStatus,
                $booking->getStatus(),
                Booking::CANCELLED_BY_CLIENT,
                Booking::CANCEL_REASON_CLIENT_BEFORE_DEADLINE
            ));
        }

        return [
            'status' => $booking->getStatus(),
            'cancelledBy' => $booking->getCancelledBy() ?? Booking::CANCELLED_BY_CLIENT,
            'cancelReason' => $booking->getCancelReason() ?? Booking::CANCEL_REASON_CLIENT_BEFORE_DEADLINE,
            'cancelledAt' => ($booking->getCancelledAt() ?? $cancelledAt)->format(DATE_ATOM),
        ];
    }

    private function resolveProviderTimezone(?string $timezone): DateTimeZone
    {
        $timezone = is_string($timezone) ? trim($timezone) : '';
        if ($timezone === '') {
            $timezone = 'Europe/Kiev';
        }

        try {
            return new DateTimeZone($timezone);
        } catch (\Throwable) {
            return new DateTimeZone('Europe/Kiev');
        }
    }
}
