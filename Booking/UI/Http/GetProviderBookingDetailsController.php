<?php

namespace App\Booking\UI\Http;

use App\Booking\Application\Query\GetProviderBookingDetails\GetProviderBookingDetailsHandler;
use App\Booking\Application\Query\GetProviderBookingDetails\GetProviderBookingDetailsQuery;
use App\Booking\Domain\ValueObject\BookingId;
use App\Identity\Domain\ValueObject\UserId;
use App\Provider\Domain\ValueObject\ProviderId;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

final class GetProviderBookingDetailsController extends AbstractController
{
    #[Route('/api/provider/{providerId}/bookings/{bookingId}', name: 'provider_booking_details', methods: ['GET'])]
    public function __invoke(string $providerId, string $bookingId, GetProviderBookingDetailsHandler $handler): JsonResponse
    {
        $user = $this->getUser();
        if (!$user || !method_exists($user, 'getId')) {
            throw new AuthenticationException();
        }

        try {
            $providerIdValue = ProviderId::fromString($providerId);
        } catch (\Throwable) {
            return new JsonResponse(['error' => 'provider_not_found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $bookingIdValue = BookingId::fromString($bookingId);
        } catch (\Throwable) {
            return new JsonResponse(['error' => 'booking_not_found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $payload = $handler(new GetProviderBookingDetailsQuery(
                UserId::fromString((string) $user->getId()),
                $providerIdValue,
                $bookingIdValue,
            ));
        } catch (\Throwable $exception) {
            return match ($exception->getMessage()) {
                'provider_not_found' => new JsonResponse(['error' => 'provider_not_found'], Response::HTTP_NOT_FOUND),
                'forbidden' => new JsonResponse(['error' => 'forbidden'], Response::HTTP_FORBIDDEN),
                default => new JsonResponse(['error' => 'booking_not_found'], Response::HTTP_NOT_FOUND),
            };
        }

        return new JsonResponse($payload, Response::HTTP_OK);
    }
}
