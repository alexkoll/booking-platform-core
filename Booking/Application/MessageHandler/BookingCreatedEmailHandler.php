<?php

namespace App\Booking\Application\MessageHandler;

use App\Booking\Application\Event\BookingCreated;
use App\Booking\Domain\Repository\BookingRepository;
use App\Booking\Domain\ValueObject\BookingId;
use App\Booking\Domain\ValueObject\BookingSource;
use App\Identity\Application\Service\UserLocaleResolver;
use App\Identity\Application\Service\UserNameResolver;
use App\Identity\Domain\Repository\UserRepository;
use App\Provider\Domain\Repository\ProviderRepository;
use App\Provider\Domain\ValueObject\ProviderId;
use App\Service\EmailService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class BookingCreatedEmailHandler
{
    public function __construct(
        private readonly ProviderRepository $providers,
        private readonly BookingRepository $bookings,
        private readonly UserRepository $users,
        private readonly UserLocaleResolver $userLocaleResolver,
        private readonly UserNameResolver $userNameResolver,
        private readonly EmailService $emailService,
        private readonly LoggerInterface $logger,
        #[Autowire('%app.frontend_url%')]
        private readonly string $frontendUrl
    ) {
    }

    public function __invoke(BookingCreated $event): void
    {
        if ($event->getSource() === BookingSource::OFFLINE) {
            return;
        }

        try {
            $provider = $this->providers->byId(ProviderId::fromString($event->getProviderId()));
            $owner = $this->users->byId($provider->getOwnerId());
            $booking = $this->bookings->byId(BookingId::fromString($event->getBookingId()));
            $ownerLocale = $this->userLocaleResolver->resolve((string) $provider->getOwnerId());

            $this->emailService->sendProviderNewBookingEmail((string) $owner->getEmail(), [
                'providerName' => (string) $provider->getName(),
                'clientName' => $event->getUserId()
                    ? $this->userNameResolver->resolve($event->getUserId())
                    : $booking->getClientName(),
                'date' => $booking->getBookingDate(),
                'time' => substr($booking->getBookingTime(), 0, 5),
                'status' => $booking->getStatus(),
                'services' => $this->serviceNames($booking->getServicesSnapshot()),
                'additionalInfo' => $booking->getAdditionalInfo(),
                'bookingUrl' => $this->providerBookingUrl($booking->getBookingDate(), (string) $booking->getAddressId()),
            ], $ownerLocale);
        } catch (\Throwable $exception) {
            $this->logger->warning('Failed to send provider new booking email.', [
                'bookingId' => $event->getBookingId(),
                'providerId' => $event->getProviderId(),
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

    private function providerBookingUrl(string $date, string $addressId): string
    {
        return rtrim($this->frontendUrl, '/') . '/provider-calendar?date=' . rawurlencode($date)
            . '&addressId=' . rawurlencode($addressId);
    }
}
