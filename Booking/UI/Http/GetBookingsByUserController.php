<?php

namespace App\Booking\UI\Http;

use App\Booking\Application\Query\BookingReadSideReader;
use App\Identity\Domain\ValueObject\UserId;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class GetBookingsByUserController extends AbstractController
{
    #[Route('/api/bookings/user/{userId}', name: 'bookings_by_user', methods: ['GET'])]
    public function __invoke(string $userId, BookingReadSideReader $bookings): JsonResponse
    {
        try {
            $id = UserId::fromString($userId);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => 'Invalid user id'], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse([
            'bookings' => $bookings->listBookingsByUserForApi($id),
        ], Response::HTTP_OK);
    }
}
