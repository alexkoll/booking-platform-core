<?php

namespace App\Booking\Application\MessageHandler;

use App\Booking\Application\Event\BookingCreated;
use App\Identity\Application\Service\UserNameResolver;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

#[AsMessageHandler]
final class BookingCreatedMercureHandler
{
    public function __construct(
        private readonly HubInterface $hub,
        private readonly UserNameResolver $userNameResolver,
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(BookingCreated $event): void
    {
        $userName = $event->getUserId()
            ? $this->userNameResolver->resolve($event->getUserId())
            : $event->getClientName();
        $payload = json_encode([
            'type' => 'booking.created',
            'booking' => [
                'id' => $event->getBookingId(),
                'providerId' => $event->getProviderId(),
                'userId' => $event->getUserId(),
                'userName' => $userName,
                'addressId' => $event->getAddressId(),
                'date' => $event->getDate(),
                'time' => $event->getTime(),
                'durationMinutes' => $event->getDurationMinutes(),
                'status' => $event->getStatus(),
                'clientName' => $event->getClientName(),
                'additionalInfo' => $event->getAdditionalInfo(),
                'source' => $event->getSource(),
            ],
        ], JSON_THROW_ON_ERROR);

        $topics = ['provider/' . $event->getProviderId() . '/'];

        try {
            $this->hub->publish(new Update($topics, $payload));
        } catch (\Throwable $exception) {
            $this->logger->warning('BookingCreated Mercure publish failed.', [
                'bookingId' => $event->getBookingId(),
                'providerId' => $event->getProviderId(),
                'exception' => $exception,
            ]);
        }
    }
}
