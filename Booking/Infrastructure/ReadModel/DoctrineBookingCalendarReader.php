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


final class DoctrineBookingCalendarReader extends DoctrineBookingReadHelper
{
    public function providerBookingSlotSources(ProviderId $providerId): array
    {
        $entities = $this->em->getRepository(BookingDoctrine::class)
            ->findBy(['providerId' => (string) $providerId], ['createdAt' => 'DESC']);

        return array_map(static function (BookingDoctrine $entity): array {
            $booking = $entity->toDomain();

            return [
                'id' => (string) $booking->getId(),
                'status' => $booking->getStatus(),
                'addressId' => (string) $booking->getAddressId(),
                'employeeId' => $booking->getEmployeeId() ? (string) $booking->getEmployeeId() : null,
                'date' => $booking->getBookingDate(),
                'time' => $booking->getBookingTime(),
                'durationMinutes' => $booking->getDurationMinutes(),
            ];
        }, $entities);
    }

    public function providerCalendarBookings(Provider $provider, string $from, string $to, ?EmployeeId $employeeId): array
    {
        $qb = $this->providerBookingsBaseQuery((string) $provider->getId(), $from, $to, null, $employeeId, null)
            ->orderBy('b.bookingDate', 'ASC')
            ->addOrderBy('b.bookingTime', 'ASC');
        /** @var BookingDoctrine[] $entities */
        $entities = $qb->getQuery()->getResult();
        $bookings = array_map(static fn(BookingDoctrine $entity): Booking => $entity->toDomain(), $entities);
        [$employeeMap] = $this->employeeMaps($provider);
        $addressMap = $this->addressMapFromProvider($provider);
        $userIds = $this->bookingUserIds($bookings);
        $users = $this->userRowsByIds($userIds);
        $profiles = $this->profileRowsByUserIds($userIds);

        return array_map(function (Booking $booking) use ($employeeMap, $addressMap, $users, $profiles): array {
            $userId = $booking->getUserId() ? (string) $booking->getUserId() : null;
            $employeeId = $booking->getEmployeeId() ? (string) $booking->getEmployeeId() : null;
            $addressId = (string) $booking->getAddressId();

            return [
                'id' => (string) $booking->getId(),
                'date' => $booking->getBookingDate(),
                'time' => $booking->getBookingTime(),
                'durationMinutes' => $booking->getDurationMinutes(),
                'addressId' => $addressId,
                'address' => $addressMap[$addressId] ?? ['id' => $addressId],
                'employeeId' => $employeeId,
                'employee' => $employeeId !== null ? ($employeeMap[$employeeId] ?? ['id' => $employeeId]) : null,
                'status' => $booking->getStatus(),
                'cancelledBy' => $booking->getCancelledBy(),
                'cancelledAt' => $booking->getCancelledAt()?->format(DATE_ATOM),
                'cancelReason' => $booking->getCancelReason(),
                'services' => $booking->getServicesSnapshot(),
                'user' => $userId !== null
                    ? $this->mapUserPayload($userId, $users, $profiles)
                    : ['id' => null, 'name' => $booking->getClientName(), 'phone' => null, 'email' => null],
            ];
        }, $bookings);
    }
}
