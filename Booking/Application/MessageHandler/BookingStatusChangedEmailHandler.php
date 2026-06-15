<?php

namespace App\Booking\Application\MessageHandler;

use App\Booking\Application\Event\BookingStatusChanged;
use App\Booking\Domain\Entity\Booking;
use App\Booking\Domain\Repository\BookingRepository;
use App\Booking\Domain\ValueObject\BookingId;
use App\Booking\Domain\ValueObject\BookingStatus;
use App\Identity\Application\Service\UserLocaleResolver;
use App\Identity\Domain\Repository\UserRepository;
use App\Identity\Domain\ValueObject\UserId;
use App\Provider\Domain\Repository\ProviderRepository;
use App\Provider\Domain\ValueObject\ProviderId;
use App\Service\EmailService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class BookingStatusChangedEmailHandler
{
    public function __construct(
        private readonly ProviderRepository $providers,
        private readonly BookingRepository $bookings,
        private readonly UserRepository $users,
        private readonly UserLocaleResolver $userLocaleResolver,
        private readonly EmailService $emailService,
        private readonly LoggerInterface $logger,
        #[Autowire('%app.frontend_url%')]
        private readonly string $frontendUrl
    ) {
    }

    public function __invoke(BookingStatusChanged $event): void
    {
        if (!$event->getUserId()) {
            return;
        }

        if (!in_array($event->getToStatus(), [BookingStatus::CONFIRMED, BookingStatus::CANCELLED], true)) {
            return;
        }

        try {
            $provider = $this->providers->byId(ProviderId::fromString($event->getProviderId()));
            $booking = $this->bookings->byId(BookingId::fromString($event->getBookingId()));
            $client = $this->users->byId(UserId::fromString($event->getUserId()));
            $clientLocale = $this->userLocaleResolver->resolve($event->getUserId());
            $context = [
                'providerName' => (string) $provider->getName(),
                'date' => $booking->getBookingDate(),
                'time' => substr($booking->getBookingTime(), 0, 5),
                'services' => $this->serviceNames($booking->getServicesSnapshot()),
                'paymentRequired' => $booking->isPaymentRequired(),
                'paymentStatus' => $booking->getPaymentStatus(),
                'bookingUrl' => rtrim($this->frontendUrl, '/') . '/bookings',
                'changedBy' => $event->getChangedBy(),
                'cancelReason' => $event->getCancelReason(),
            ];

            if ($event->getToStatus() === BookingStatus::CONFIRMED) {
                $this->emailService->sendClientBookingConfirmedEmail((string) $client->getEmail(), $context, $clientLocale);
                return;
            }

            if ($event->getChangedBy() === Booking::CANCELLED_BY_CLIENT) {
                $this->emailService->sendClientBookingSelfCancelledEmail((string) $client->getEmail(), $context, $clientLocale);
                return;
            }

            $this->emailService->sendClientBookingCancelledEmail((string) $client->getEmail(), $context, $clientLocale);
        } catch (\Throwable $exception) {
            $this->logger->warning('Failed to send client booking status email.', [
                'bookingId' => $event->getBookingId(),
                'providerId' => $event->getProviderId(),
                'userId' => $event->getUserId(),
                'toStatus' => $event->getToStatus(),
                'exception' => $exception,
            ]);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $snapshot
     * @return string[]
     */
    private function serviceNames(array $snapshot): array
    {
        return array_values(array_filter(array_map(
            static fn(array $item): ?string => isset($item['name']) ? (string) $item['name'] : null,
            $snapshot
        )));
    }
}
