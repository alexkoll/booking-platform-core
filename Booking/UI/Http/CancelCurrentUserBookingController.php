<?php

namespace App\Booking\UI\Http;

use App\Booking\Application\Command\CancelBookingByClient\CancelBookingByClientCommand;
use App\Booking\Application\Command\CancelBookingByClient\CancelBookingByClientHandler;
use App\Shared\Http\ApiResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

final class CancelCurrentUserBookingController extends AbstractController
{
    #[Route('/api/bookings/{id}/cancel', name: 'booking_client_cancel', methods: ['POST'])]
    public function __invoke(string $id, CancelBookingByClientHandler $handler): JsonResponse
    {
        $user = $this->getUser();
        if (!$user || !method_exists($user, 'getId')) {
            throw new AuthenticationException();
        }

        try {
            $payload = $handler(new CancelBookingByClientCommand($id, (string) $user->getId()));
        } catch (\Throwable $exception) {
            $message = $exception->getMessage();
            if ($message === 'Booking not found') {
                return ApiResponse::error($message, JsonResponse::HTTP_NOT_FOUND);
            }

            return ApiResponse::error($message);
        }

        return ApiResponse::success($payload);
    }
}
