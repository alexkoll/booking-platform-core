<?php

namespace App\Booking\UI\Http;

use App\Booking\Application\Command\CreateBooking\CreateBookingCommand;
use App\Booking\Application\Command\CreateBooking\CreateBookingHandler;
use App\Provider\Infrastructure\Cache\PublicProviderPageBootstrapCacheInvalidator;
use App\Service\ValidationErrorFormatter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

final class CreateBookingController extends AbstractController
{
    #[Route('/api/booking', name: 'booking_create', methods: ['POST'])]
    public function __invoke(Request $request, CreateBookingHandler $handler, ValidatorInterface $validator, \Symfony\Contracts\Translation\TranslatorInterface $translator, PublicProviderPageBootstrapCacheInvalidator $publicPageCache): JsonResponse
    {
        $user = $this->getUser();
        if (!$user || !method_exists($user, 'getId')) {
            throw new AuthenticationException();
        }

        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new JsonResponse(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        $providerIdRaw = (string) ($data['providerId'] ?? '');
        $addressIdRaw = (string) ($data['addressId'] ?? '');
        $dateRaw = (string) ($data['date'] ?? '');
        $timeRaw = (string) ($data['time'] ?? '');
        $employeeIdRaw = isset($data['employeeId']) ? (string) $data['employeeId'] : null;

        $employeeServiceIds = $data['employeeServiceIds'] ?? null;
        if ($employeeServiceIds === null) {
            $employeeServiceIds = $data['serviceIds'] ?? null;
        }
        if ($employeeServiceIds === null && isset($data['serviceId'])) {
            $employeeServiceIds = [$data['serviceId']];
        }

        $rawPromoIds = is_array($data['promoIds'] ?? null) ? $data['promoIds'] : [];
        $promoIds = array_values(array_filter(
            $rawPromoIds,
            static fn($value) => is_string($value) && $value !== ''
        ));
        $termsAccepted = filter_var($data['termsAccepted'] ?? false, FILTER_VALIDATE_BOOL);

        $command = new CreateBookingCommand(
            $providerIdRaw,
            $addressIdRaw,
            (string) $user->getId(),
            isset($data['clientName']) ? (string) $data['clientName'] : null,
            isset($data['additionalInfo']) ? (string) $data['additionalInfo'] : null,
            $employeeIdRaw,
            $dateRaw,
            $timeRaw,
            is_array($employeeServiceIds) ? $employeeServiceIds : [],
            $promoIds,
            $termsAccepted
        );

        $violations = $validator->validate($command);
        if (count($violations) > 0) {
            return new JsonResponse(['errors' => ValidationErrorFormatter::toArray($violations)], Response::HTTP_BAD_REQUEST);
        }

        $previousLocale = $translator->getLocale();
        $translator->setLocale($request->getLocale());
        try {
            $booking = $handler($command);
        } catch (\Throwable $e) {
            $translator->setLocale($previousLocale);
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
        $translator->setLocale($previousLocale);
        $publicPageCache->invalidateProvider($booking->getProviderId());

        return new JsonResponse([
            'booking' => [
                'id' => (string) $booking->getId(),
                'providerId' => (string) $booking->getProviderId(),
                'userId' => (string) $booking->getUserId(),
                'addressId' => (string) $booking->getAddressId(),
                'status' => $booking->getStatus(),
                'date' => $booking->getBookingDate(),
                'time' => $booking->getBookingTime(),
                'durationMinutes' => $booking->getDurationMinutes(),
                'services' => $booking->getServicesSnapshot(),
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
                'terms' => [
                    'version' => $booking->getTermsVersion(),
                    'acceptedAt' => $booking->getTermsAcceptedAt()?->format(DATE_ATOM),
                ],
            ]
        ], Response::HTTP_CREATED);
    }
}
