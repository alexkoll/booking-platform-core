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


final class DoctrineBookingSummaryReader extends DoctrineBookingReadHelper
{
    public function statsForProviderAndUser(ProviderId $providerId, UserId $userId): array
    {
        $rows = $this->em->createQueryBuilder()
            ->select('b.status AS status, COUNT(b.id) AS total')
            ->from(BookingDoctrine::class, 'b')
            ->where('b.providerId = :providerId')
            ->andWhere('b.userId = :userId')
            ->setParameter('providerId', (string) $providerId)
            ->setParameter('userId', (string) $userId)
            ->groupBy('b.status')
            ->getQuery()
            ->getArrayResult();

        $stats = [
            'total' => 0,
            'completed' => 0,
            'no_show' => 0,
            'cancelled' => 0,
            'confirmed' => 0,
            'pending' => 0,
        ];

        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            $count = (int) ($row['total'] ?? 0);
            $stats['total'] += $count;
            if (array_key_exists($status, $stats)) {
                $stats[$status] = $count;
            }
        }

        return $stats;
    }

    public function countCompletedForProvider(ProviderId $providerId): int
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(b.id)')
            ->from(BookingDoctrine::class, 'b')
            ->where('b.providerId = :providerId')
            ->andWhere('b.status = :status')
            ->setParameter('providerId', (string) $providerId)
            ->setParameter('status', 'completed')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function userProfileBookingSummary(UserId $userId): array
    {
        $bookingsCount = (int) $this->em->createQueryBuilder()
            ->select('COUNT(b.id)')
            ->from(BookingDoctrine::class, 'b')
            ->where('b.userId = :userId')
            ->setParameter('userId', (string) $userId)
            ->getQuery()
            ->getSingleScalarResult();

        $now = new \DateTimeImmutable();
        /** @var BookingDoctrine[] $entities */
        $entities = $this->em->createQueryBuilder()
            ->select('b')
            ->from(BookingDoctrine::class, 'b')
            ->where('b.userId = :userId')
            ->andWhere('b.status <> :cancelled')
            ->andWhere('(b.bookingDate > :today OR (b.bookingDate = :today AND b.bookingTime >= :nowTime))')
            ->setParameter('userId', (string) $userId)
            ->setParameter('cancelled', 'cancelled')
            ->setParameter('today', \DateTimeImmutable::createFromFormat('!Y-m-d', $now->format('Y-m-d')))
            ->setParameter('nowTime', \DateTimeImmutable::createFromFormat('!H:i:s', $now->format('H:i:s')))
            ->orderBy('b.bookingDate', 'ASC')
            ->addOrderBy('b.bookingTime', 'ASC')
            ->setMaxResults(3)
            ->getQuery()
            ->getResult();

        $bookings = array_map(static fn(BookingDoctrine $entity): Booking => $entity->toDomain(), $entities);
        $providerIds = [];
        foreach ($bookings as $booking) {
            $providerIds[(string) $booking->getProviderId()] = true;
        }
        $providers = $this->providerRowsByIds(array_keys($providerIds));

        return [
            'bookingsCount' => $bookingsCount,
            'bookingsPreview' => array_map(
                function (Booking $booking) use ($providers): array {
                    $providerId = (string) $booking->getProviderId();

                    return [
                        'id' => (string) $booking->getId(),
                        'provider' => $providers[$providerId] ?? ['id' => $providerId, 'name' => null, 'slug' => null],
                        'status' => $booking->getStatus(),
                        'date' => $booking->getBookingDate(),
                        'time' => $booking->getBookingTime(),
                        'durationMinutes' => $booking->getDurationMinutes(),
                    ];
                },
                $bookings
            ),
        ];
    }

    public function providerProfileBookingSummary(ProviderId $providerId): array
    {
        $completedOrders = (int) $this->em->createQueryBuilder()
            ->select('COUNT(b.id)')
            ->from(BookingDoctrine::class, 'b')
            ->where('b.providerId = :providerId')
            ->andWhere('b.status = :completed')
            ->setParameter('providerId', (string) $providerId)
            ->setParameter('completed', 'completed')
            ->getQuery()
            ->getSingleScalarResult();

        $now = new \DateTimeImmutable();
        /** @var BookingDoctrine[] $entities */
        $entities = $this->em->createQueryBuilder()
            ->select('b')
            ->from(BookingDoctrine::class, 'b')
            ->where('b.providerId = :providerId')
            ->andWhere('b.status <> :cancelled')
            ->andWhere('(b.bookingDate > :today OR (b.bookingDate = :today AND b.bookingTime >= :nowTime))')
            ->setParameter('providerId', (string) $providerId)
            ->setParameter('cancelled', 'cancelled')
            ->setParameter('today', \DateTimeImmutable::createFromFormat('!Y-m-d', $now->format('Y-m-d')))
            ->setParameter('nowTime', \DateTimeImmutable::createFromFormat('!H:i:s', $now->format('H:i:s')))
            ->orderBy('b.bookingDate', 'ASC')
            ->addOrderBy('b.bookingTime', 'ASC')
            ->setMaxResults(4)
            ->getQuery()
            ->getResult();

        $bookings = array_map(static fn(BookingDoctrine $entity): Booking => $entity->toDomain(), $entities);
        $userIds = $this->bookingUserIds($bookings);
        $users = $this->userRowsByIds($userIds);
        $profiles = $this->profileRowsByUserIds($userIds);

        return [
            'completedOrders' => $completedOrders,
            'bookingsPreview' => array_map(function (Booking $booking) use ($users, $profiles): array {
                $userId = $booking->getUserId() ? (string) $booking->getUserId() : null;
                [$totalPrice, $currency] = $this->calculateSnapshotTotal($booking->getServicesSnapshot());

                return [
                    'id' => (string) $booking->getId(),
                    'date' => $booking->getBookingDate(),
                    'time' => $booking->getBookingTime(),
                    'durationMinutes' => $booking->getDurationMinutes(),
                    'status' => $booking->getStatus(),
                    'services' => $booking->getServicesSnapshot(),
                    'totalPrice' => $totalPrice,
                    'currency' => $currency,
                    'user' => $userId !== null
                        ? $this->mapUserPayload($userId, $users, $profiles)
                        : ['id' => '', 'name' => $booking->getClientName(), 'avatarUrl' => null],
                ];
            }, $bookings),
        ];
    }
}
