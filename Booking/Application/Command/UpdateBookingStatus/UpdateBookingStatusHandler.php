<?php

namespace App\Booking\Application\Command\UpdateBookingStatus;

use App\Booking\Domain\Repository\BookingRepository;
use App\Booking\Domain\ValueObject\BookingId;
use App\Booking\Application\Event\BookingStatusChanged;
use App\Booking\Domain\Entity\Booking;
use App\Payment\Application\Service\RefundPaymentService;
use App\Provider\Domain\Repository\ProviderRepository;
use App\Provider\Infrastructure\Cache\PublicProviderPageBootstrapCacheInvalidator;
use App\Identity\Domain\ValueObject\UserId;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Symfony\Component\Messenger\MessageBusInterface;

final class UpdateBookingStatusHandler
{
    private const PAYMENT_DEADLINE_MINUTES = 30;

    public function __construct(
        private readonly BookingRepository $bookings,
        private readonly ProviderRepository $providers,
        private readonly RefundPaymentService $refundPayment,
        private readonly MessageBusInterface $bus,
        private readonly PublicProviderPageBootstrapCacheInvalidator $publicPageCache
    ) {
    }

    public function __invoke(UpdateBookingStatusCommand $command, string $ownerUserId): void
    {
        $bookingId = BookingId::fromString($command->bookingId);
        $booking = $this->bookings->byId($bookingId);

        $provider = $this->providers->byOwnerId(UserId::fromString($ownerUserId));
        if (!$provider) {
            throw new RuntimeException('Provider not found');
        }

        if ((string) $booking->getProviderId() !== (string) $provider->getId()) {
            throw new RuntimeException('Access denied');
        }

        if ($command->status === \App\Booking\Domain\ValueObject\BookingStatus::COMPLETED
            && $booking->getStatus() !== \App\Booking\Domain\ValueObject\BookingStatus::CONFIRMED) {
            throw new RuntimeException('Only confirmed bookings can be completed');
        }

        if ($command->status === \App\Booking\Domain\ValueObject\BookingStatus::NO_SHOW
            && $booking->getStatus() !== \App\Booking\Domain\ValueObject\BookingStatus::CONFIRMED) {
            throw new RuntimeException('Only confirmed bookings can be marked as no show');
        }

        $providerTimezone = $this->resolveProviderTimezone($provider->getTimezone());
        $bookingStartsAt = $booking->getStartsAtIn($providerTimezone);

        if ($command->status === \App\Booking\Domain\ValueObject\BookingStatus::CANCELLED
            && $bookingStartsAt <= new DateTimeImmutable('now', $providerTimezone)) {
            throw new RuntimeException('Booking can only be cancelled before appointment start');
        }

        if ($command->status === \App\Booking\Domain\ValueObject\BookingStatus::CONFIRMED && $booking->isPaymentRequired()) {
            if ($booking->getPaymentDeadlineAt() === null) {
                $booking->markPaymentPending(new \DateTimeImmutable('+' . self::PAYMENT_DEADLINE_MINUTES . ' minutes'));
            }
        }

        $oldStatus = $booking->getStatus();
        if ($command->status === \App\Booking\Domain\ValueObject\BookingStatus::CANCELLED) {
            $booking->cancel(
                Booking::CANCELLED_BY_PROVIDER,
                Booking::CANCEL_REASON_PROVIDER_BEFORE_START,
                new DateTimeImmutable()
            );
        } elseif ($command->status === \App\Booking\Domain\ValueObject\BookingStatus::CONFIRMED) {
            $booking->confirm();
        } elseif ($command->status === \App\Booking\Domain\ValueObject\BookingStatus::COMPLETED) {
            $booking->complete();
        } elseif ($command->status === \App\Booking\Domain\ValueObject\BookingStatus::NO_SHOW) {
            $booking->markNoShow();
        } elseif ($command->status === \App\Booking\Domain\ValueObject\BookingStatus::EXPIRED) {
            $booking->expire();
        } else {
            $booking->changeStatus($command->status);
        }
        $this->bookings->add($booking);
        $this->publicPageCache->invalidateProvider($booking->getProviderId());

        if ($command->status === \App\Booking\Domain\ValueObject\BookingStatus::CANCELLED
            && $booking->getPaymentStatus() === Booking::PAYMENT_STATUS_PAID) {
            $this->refundPayment->refundByBookingId($booking->getId(), Booking::CANCEL_REASON_PROVIDER_BEFORE_START);
        }

        if ($oldStatus !== $command->status) {
            $this->bus->dispatch(new BookingStatusChanged(
                (string) $booking->getId(),
                (string) $booking->getProviderId(),
                $booking->getUserId() ? (string) $booking->getUserId() : null,
                $oldStatus,
                $command->status,
                $command->status === \App\Booking\Domain\ValueObject\BookingStatus::CANCELLED ? Booking::CANCELLED_BY_PROVIDER : null,
                $command->status === \App\Booking\Domain\ValueObject\BookingStatus::CANCELLED ? Booking::CANCEL_REASON_PROVIDER_BEFORE_START : null
            ));
        }
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
