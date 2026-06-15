<?php

namespace App\Booking\UI\Http;

use App\Booking\Application\Command\CreateOfflineBooking\CreateOfflineBookingCommand;
use App\Booking\Application\Command\CreateOfflineBooking\CreateOfflineBookingHandler;
use App\Provider\Domain\Repository\ProviderRepository;
use App\Provider\Infrastructure\Cache\PublicProviderPageBootstrapCacheInvalidator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

final class CreateOfflineBookingController extends AbstractController
{
    public function __construct(
        private readonly CreateOfflineBookingHandler $handler,
        private readonly ProviderRepository $providers,
        private readonly PublicProviderPageBootstrapCacheInvalidator $publicPageCache
    )
    {
    }

    #[Route('/api/provider/{providerId}/offline-bookings', name: 'create_offline_booking', methods: ['POST'])]
    public function __invoke(string $providerId, Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user || !method_exists($user, 'getId')) {
            throw new AuthenticationException();
        }

        try {
            $provider = $this->providers->byId(\App\Provider\Domain\ValueObject\ProviderId::fromString($providerId));
        } catch (\Throwable) {
            return new JsonResponse(['error' => 'Provider not found'], Response::HTTP_BAD_REQUEST);
        }
        if ((string) $provider->getOwnerId() !== (string) $user->getId()) {
            return new JsonResponse(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $payload = json_decode($request->getContent(), true) ?? [];

        $command = new CreateOfflineBookingCommand(
            providerId: $providerId,
            addressId: (string) ($payload['addressId'] ?? ''),
            employeeId: $payload['employeeId'] ?? null,
            date: (string) ($payload['date'] ?? ''),
            time: (string) ($payload['time'] ?? ''),
            slots: is_array($payload['slots'] ?? null) ? $payload['slots'] : [],
            serviceIds: $payload['serviceIds'] ?? [],
            clientName: (string) ($payload['clientName'] ?? ''),
            additionalInfo: $payload['additionalInfo'] ?? null,
            totalPrice: isset($payload['totalPrice']) ? (float) $payload['totalPrice'] : null,
        );

        try {
            $booking = ($this->handler)($command);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
        $this->publicPageCache->invalidateProvider($booking->getProviderId());

        return new JsonResponse([
            'id' => (string) $booking->getId(),
            'status' => $booking->getStatus(),
        ], Response::HTTP_CREATED);
    }
}
