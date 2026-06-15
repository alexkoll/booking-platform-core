<?php

namespace App\Booking\Application\MessageHandler;

use App\Booking\Application\Event\BookingStatusChanged;
use App\Booking\Domain\Entity\Booking;
use App\Booking\Domain\Repository\BookingRepository;
use App\Booking\Domain\ValueObject\BookingId;
use App\Booking\Domain\ValueObject\BookingStatus;
use App\Identity\Application\Service\UserLocaleResolver;
use App\Identity\Application\Service\UserNameResolver;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Repository\UserRepository;
use App\Identity\Domain\Repository\UserTelegramRepository;
use App\Identity\Domain\ValueObject\UserId;
use App\I18n\Application\Service\RuntimeTranslationService;
use App\Provider\Domain\Entity\Employee;
use App\Provider\Domain\Repository\EmployeeRepository;
use App\Provider\Domain\Repository\ProviderRepository;
use App\Provider\Domain\ValueObject\EmployeeId;
use App\Provider\Domain\ValueObject\ProviderId;
use App\Service\EmailService;
use App\Service\TelegramClient;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class BookingClientCancelledProviderNotificationHandler
{
    public function __construct(
        private readonly ProviderRepository $providers,
        private readonly BookingRepository $bookings,
        private readonly EmployeeRepository $employees,
        private readonly UserRepository $users,
        private readonly UserTelegramRepository $telegrams,
        private readonly UserLocaleResolver $userLocaleResolver,
        private readonly UserNameResolver $userNameResolver,
        private readonly RuntimeTranslationService $translations,
        private readonly EmailService $emailService,
        private readonly TelegramClient $telegramClient,
        private readonly LoggerInterface $logger,
        #[Autowire('%app.frontend_url%')]
        private readonly string $frontendUrl
    ) {
    }

    public function __invoke(BookingStatusChanged $event): void
    {
        if ($event->getToStatus() !== BookingStatus::CANCELLED) {
            return;
        }

        if ($event->getChangedBy() !== Booking::CANCELLED_BY_CLIENT) {
            return;
        }

        try {
            $provider = $this->providers->byId(ProviderId::fromString($event->getProviderId()));
            $booking = $this->bookings->byId(BookingId::fromString($event->getBookingId()));
        } catch (\Throwable $exception) {
            $this->logger->warning('Failed to load booking client cancellation notification context.', [
                'bookingId' => $event->getBookingId(),
                'providerId' => $event->getProviderId(),
                'exception' => $exception,
            ]);
            return;
        }

        $clientName = $event->getUserId()
            ? $this->userNameResolver->resolve($event->getUserId())
            : $booking->getClientName();
        $context = [
            'providerName' => (string) $provider->getName(),
            'clientName' => $clientName,
            'date' => $booking->getBookingDate(),
            'time' => substr($booking->getBookingTime(), 0, 5),
            'status' => $booking->getStatus(),
            'services' => $this->serviceNames($booking->getServicesSnapshot()),
            'bookingUrl' => $this->providerBookingUrl($booking->getBookingDate(), (string) $booking->getAddressId()),
            'changedBy' => $event->getChangedBy(),
            'cancelReason' => $event->getCancelReason(),
        ];

        $recipients = [];
        $this->addRecipient($recipients, (string) $provider->getOwnerId());

        $employee = $this->resolveEmployee($booking->getEmployeeId() ? (string) $booking->getEmployeeId() : null);
        if ($employee?->getUserId()) {
            $this->addRecipient($recipients, (string) $employee->getUserId());
        }

        foreach ($recipients as $recipient) {
            $this->sendEmail($recipient, $context, $event);
            $this->sendTelegram($recipient, $context, $event);
        }
    }

    /**
     * @param array<string, User> $recipients
     */
    private function addRecipient(array &$recipients, string $userId): void
    {
        if (isset($recipients[$userId])) {
            return;
        }

        try {
            $recipients[$userId] = $this->users->byId(UserId::fromString($userId));
        } catch (\Throwable) {
            // Missing linked users are ignored: there is no email or Telegram target.
        }
    }

    private function resolveEmployee(?string $employeeId): ?Employee
    {
        if ($employeeId === null || $employeeId === '') {
            return null;
        }

        try {
            return $this->employees->byId(EmployeeId::fromString($employeeId));
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function sendEmail(User $recipient, array $context, BookingStatusChanged $event): void
    {
        try {
            $this->emailService->sendProviderClientCancelledBookingEmail(
                (string) $recipient->getEmail(),
                $context,
                $this->userLocaleResolver->resolve((string) $recipient->getId())
            );
        } catch (\Throwable $exception) {
            $this->logger->warning('Failed to send provider client-cancelled booking email.', [
                'bookingId' => $event->getBookingId(),
                'recipientUserId' => (string) $recipient->getId(),
                'exception' => $exception,
            ]);
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function sendTelegram(User $recipient, array $context, BookingStatusChanged $event): void
    {
        try {
            $telegram = $this->telegrams->byUserId($recipient->getId());
            if (!$telegram || !$telegram->isActive()) {
                return;
            }

            $locale = $this->userLocaleResolver->resolve((string) $recipient->getId());
            $text = $this->translations->trans('telegram.booking_client_cancelled', $locale, [
                'date' => (string) $context['date'],
                'time' => (string) $context['time'],
                'client' => (string) $context['clientName'],
            ]);

            $this->telegramClient->sendMessage($telegram->getChatId(), $text);
        } catch (\Throwable $exception) {
            $this->logger->warning('Failed to send provider client-cancelled booking Telegram message.', [
                'bookingId' => $event->getBookingId(),
                'recipientUserId' => (string) $recipient->getId(),
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
