<?php

namespace App\Booking\Infrastructure\ReadModel;

use App\Booking\Application\Query\BookingReadSideReader;
use App\Booking\Application\Query\GetCurrentUserBookings\CurrentUserBookingsReader;
use App\Booking\Application\Query\GetProviderBookingDetails\ProviderBookingDetailsReader;
use App\Booking\Application\Query\GetProviderBookings\ProviderBookingsReader;
use App\Booking\Application\Support\BookingTotalsView;
use App\Booking\Domain\Entity\Booking;
use App\Booking\Domain\ValueObject\BookingId;
use App\Booking\Infrastructure\Doctrine\Entity\BookingDoctrine;
use App\Identity\Domain\ValueObject\UserId;
use App\Identity\Infrastructure\Doctrine\Entity\UserDoctrine;
use App\Identity\Infrastructure\Doctrine\Entity\UserProfileDoctrine;
use App\Payment\Domain\Entity\ProviderCommercialTerms;
use App\Payment\Infrastructure\Doctrine\Entity\PaymentDoctrine;
use App\Payment\Infrastructure\Doctrine\Entity\ProviderCommercialTermsDoctrine;
use App\Provider\Domain\Entity\Provider;
use App\Provider\Domain\Entity\ServiceType;
use App\Provider\Domain\ValueObject\EmployeeId;
use App\Provider\Domain\ValueObject\ProviderId;
use App\Provider\Infrastructure\Doctrine\Entity\ProviderDoctrine;
use App\Provider\Infrastructure\Doctrine\Entity\ProviderEmployeeServiceDoctrine;
use App\Provider\Infrastructure\Doctrine\Entity\ProviderLocationDoctrine;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;


final class DoctrineProviderBookingDetailsReader extends DoctrineBookingReadHelper implements ProviderBookingDetailsReader
{
    public function getProviderBookingDetails(Provider $provider, BookingId $bookingId): ?array
    {
        $entity = $this->em->find(BookingDoctrine::class, (string) $bookingId);
        if (!$entity) {
            return null;
        }
        $booking = $entity->toDomain();
        if ((string) $booking->getProviderId() !== (string) $provider->getId()) {
            return null;
        }

        $userPayload = [
            'id' => null,
            'name' => $booking->getClientName() ?: null,
            'phone' => null,
            'email' => null,
            'avatarUrl' => null,
        ];
        if ($booking->getUserId()) {
            $userId = (string) $booking->getUserId();
            $users = $this->userRowsByIds([$userId]);
            $profiles = $this->profileRowsByUserIds([$userId]);
            $userPayload = $this->mapUserPayload($userId, $users, $profiles);
        }

        $employeePayload = $this->employeePayloadForBooking($provider, $booking);
        $addressPayload = $this->addressPayloadForBooking($provider, $booking);
        $services = $this->enrichServicesSnapshot($booking->getServicesSnapshot(), $provider);
        $totals = BookingTotalsView::build($services, $booking->getCustomTotalPrice());
        $payment = $this->paymentRowByBookingId((string) $booking->getId());
        $commissionPercent = $this->commissionPercent((string) $provider->getId());
        $paymentPaidAt = $booking->getPaidAt();
        if ($paymentPaidAt === null && ($payment['status'] ?? null) === 'paid' && isset($payment['updatedAt']) && $payment['updatedAt'] instanceof \DateTimeInterface) {
            $paymentPaidAt = \DateTimeImmutable::createFromInterface($payment['updatedAt']);
        }

        return [
            'booking' => [
                'id' => (string) $booking->getId(),
                'providerId' => (string) $booking->getProviderId(),
                'userId' => $booking->getUserId() ? (string) $booking->getUserId() : null,
                'clientName' => $booking->getClientName(),
                'additionalInfo' => $booking->getAdditionalInfo(),
                'addressId' => (string) $booking->getAddressId(),
                'employeeId' => $booking->getEmployeeId() ? (string) $booking->getEmployeeId() : null,
                'status' => $booking->getStatus(),
                'cancelledBy' => $booking->getCancelledBy(),
                'cancelledAt' => $booking->getCancelledAt()?->format(DATE_ATOM),
                'cancelReason' => $booking->getCancelReason(),
                'date' => $booking->getBookingDate(),
                'time' => $booking->getBookingTime(),
                'durationMinutes' => $booking->getDurationMinutes(),
                'services' => $services,
                'servicesTotalPrice' => $totals['servicesTotalPrice'],
                'customTotalPrice' => $totals['customTotalPrice'],
                'totalPrice' => $totals['totalPrice'],
                'currency' => $totals['currency'],
                'user' => $userPayload,
                'employee' => $employeePayload,
                'address' => $addressPayload,
                'createdAt' => $booking->getCreatedAt()->format(DATE_ATOM),
                'updatedAt' => $booking->getUpdatedAt()->format(DATE_ATOM),
                'payment' => [
                    'required' => $booking->isPaymentRequired(),
                    'type' => $booking->getPaymentType(),
                    'depositMode' => $booking->getDepositMode(),
                    'depositValue' => $booking->getDepositValue(),
                    'amountDue' => $booking->getPaymentAmountDue(),
                    'amountDueMinor' => $payment['amountMinor'] ?? null,
                    'currency' => $booking->getCurrency(),
                    'status' => $booking->getPaymentStatus(),
                    'deadlineAt' => $booking->getPaymentDeadlineAt()?->format(DATE_ATOM),
                    'paidAt' => $paymentPaidAt?->format(DATE_ATOM),
                    'paymentIntentId' => $booking->getPaymentIntentId(),
                    'paymentProvider' => $booking->getPaymentProvider(),
                    'commissionPercent' => $commissionPercent,
                    'grossAmountMinor' => $payment['amountMinor'] ?? null,
                    'grossAmount' => $this->minorToMajor($payment['amountMinor'] ?? null),
                    'providerAmountMinor' => $payment['providerAmountMinor'] ?? null,
                    'providerAmount' => $this->minorToMajor($payment['providerAmountMinor'] ?? null),
                    'platformFeeMinor' => $payment['platformFeeMinor'] ?? null,
                    'platformFee' => $this->minorToMajor($payment['platformFeeMinor'] ?? null),
                    'platformFeePercentSnapshot' => isset($payment['platformFeePercentSnapshot']) ? (float) $payment['platformFeePercentSnapshot'] : null,
                    'paymentEntityStatus' => $payment['status'] ?? null,
                    'paymentEntityUpdatedAt' => isset($payment['updatedAt']) && $payment['updatedAt'] instanceof \DateTimeInterface
                        ? $payment['updatedAt']->format(DATE_ATOM)
                        : null,
                ],
            ],
        ];
    }
}
