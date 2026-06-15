<?php

namespace App\Booking\UI\Http;

use App\Booking\Domain\Repository\BookingRepository;
use App\Booking\Domain\ValueObject\BookingId;
use App\Provider\Domain\Repository\ProviderRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

final class GetBookingPaymentController extends AbstractController
{
    #[Route('/api/bookings/{id}/payment', name: 'booking_payment_info', methods: ['GET'])]
    public function __invoke(
        string $id,
        BookingRepository $bookings,
        ProviderRepository $providers
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user || !method_exists($user, 'getId')) {
            throw new AuthenticationException();
        }

        try {
            $bookingId = BookingId::fromString($id);
            $booking = $bookings->byId($bookingId);
        } catch (\Throwable) {
            return new JsonResponse(['error' => 'booking_not_found'], Response::HTTP_NOT_FOUND);
        }

        $isOwner = (string) $booking->getUserId() === (string) $user->getId();
        $isProviderOwner = false;

        if (!$isOwner) {
            try {
                $provider = $providers->byId($booking->getProviderId());
                $isProviderOwner = (string) $provider->getOwnerId() === (string) $user->getId();
            } catch (\Throwable) {
                $isProviderOwner = false;
            }
        }

        if (!$isOwner && !$isProviderOwner) {
            return new JsonResponse(['error' => 'forbidden'], Response::HTTP_FORBIDDEN);
        }

        return new JsonResponse([
            'bookingId' => (string) $booking->getId(),
            'payment' => [
                'required' => $booking->isPaymentRequired(),
                'type' => $booking->getPaymentType(),
                'depositMode' => $booking->getDepositMode(),
                'depositValue' => $booking->getDepositValue(),
                'amountDue' => $booking->getPaymentAmountDue(),
                'currency' => $booking->getCurrency(),
                'status' => $booking->getPaymentStatus(),
                'deadlineAt' => $booking->getPaymentDeadlineAt()?->format(DATE_ATOM),
                'paidAt' => $booking->getPaidAt()?->format(DATE_ATOM),
                'paymentIntentId' => $booking->getPaymentIntentId(),
                'paymentProvider' => $booking->getPaymentProvider(),
            ],
        ], Response::HTTP_OK);
    }
}
