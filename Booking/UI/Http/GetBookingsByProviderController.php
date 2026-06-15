<?php

namespace App\Booking\UI\Http;

use App\Booking\Application\Query\BookingReadSideReader;
use App\Provider\Domain\ValueObject\ProviderId;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class GetBookingsByProviderController extends AbstractController
{
    #[Route('/api/bookings/provider/{providerId}', name: 'bookings_by_provider', methods: ['GET'])]
    public function __invoke(string $providerId, BookingReadSideReader $bookings): JsonResponse
    {
        try {
            $id = ProviderId::fromString($providerId);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => 'Invalid provider id'], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse([
            'bookings' => $bookings->listBookingsByProviderForApi($id),
        ], Response::HTTP_OK);
    }
}
