<?php

namespace App\Booking\Application\MessageHandler;

use App\Booking\Application\Event\BookingCreated;
use App\Identity\Application\Service\UserNameResolver;
use App\Identity\Application\Service\UserLocaleResolver;
use App\Identity\Domain\Repository\UserTelegramRepository;
use App\I18n\Application\Service\RuntimeTranslationService;
use App\Provider\Domain\Repository\ProviderRepository;
use App\Service\TelegramClient;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class BookingCreatedTelegramHandler
{
    public function __construct(
        private readonly ProviderRepository $providers,
        private readonly UserTelegramRepository $userTelegramRepository,
        private readonly UserNameResolver $userNameResolver,
        private readonly TelegramClient $telegramClient,
        private readonly RuntimeTranslationService $translations,
        private readonly UserLocaleResolver $userLocaleResolver
    ) {
    }

    public function __invoke(BookingCreated $event): void
    {
        try {
            $provider = $this->providers->byId(\App\Provider\Domain\ValueObject\ProviderId::fromString($event->getProviderId()));
        } catch (\Throwable) {
            return;
        }

        $ownerId = $provider->getOwnerId();
        $userTelegram = $this->userTelegramRepository->byUserId($ownerId);
        if (!$userTelegram || !$userTelegram->isActive()) {
            return;
        }

        $clientName = $event->getUserId()
            ? $this->userNameResolver->resolve($event->getUserId())
            : $event->getClientName();
        $time = $event->getTime();
        $time = is_string($time) ? substr($time, 0, 5) : $time;

        $text = $this->translations->trans(
            'telegram.new_booking',
            $this->userLocaleResolver->resolve((string) $ownerId),
            [
                'date' => $event->getDate(),
                'time' => $time,
                'client' => $clientName,
            ]
        );

        $this->telegramClient->sendMessage($userTelegram->getChatId(), $text);
    }
}
