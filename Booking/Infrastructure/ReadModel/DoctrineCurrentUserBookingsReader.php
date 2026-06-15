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


final class DoctrineCurrentUserBookingsReader extends DoctrineBookingReadHelper implements CurrentUserBookingsReader
{
    public function listProviderIdsForUser(UserId $userId): array
    {
        $rows = $this->em->createQueryBuilder()
            ->select('DISTINCT b.providerId AS providerId')
            ->from(BookingDoctrine::class, 'b')
            ->where('b.userId = :userId')
            ->setParameter('userId', (string) $userId)
            ->getQuery()
            ->getArrayResult();

        return array_values(array_filter(array_map(
            static fn(array $row): string => (string) ($row['providerId'] ?? ''),
            $rows
        )));
    }

    public function listCurrentUserBookings(
        UserId $userId,
        int $page,
        int $limit,
        ?ProviderId $providerId,
        ?string $dateFrom,
        ?string $dateTo,
        ?string $status,
        ?string $period,
        array $providerLocalDatesById
    ): array {
        $page = max(1, $page);
        $limit = max(1, min(50, $limit));
        $offset = ($page - 1) * $limit;

        $qb = $this->em->createQueryBuilder()
            ->select('b')
            ->from(BookingDoctrine::class, 'b')
            ->where('b.userId = :userId')
            ->setParameter('userId', (string) $userId);

        if ($providerId) {
            $qb->andWhere('b.providerId = :providerId')
                ->setParameter('providerId', (string) $providerId);
        }
        $this->applyClientDateFilter($qb, 'b', $dateFrom, $dateTo, $period, $providerLocalDatesById);
        if ($status) {
            $qb->andWhere('b.status = :status')
                ->setParameter('status', $status);
        }
        $qb->orderBy('b.createdAt', 'DESC')
            ->addOrderBy('b.bookingDate', 'DESC')
            ->addOrderBy('b.bookingTime', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        /** @var BookingDoctrine[] $entities */
        $entities = $qb->getQuery()->getResult();
        $bookings = array_map(static fn(BookingDoctrine $entity): Booking => $entity->toDomain(), $entities);

        $countQb = $this->em->createQueryBuilder()
            ->select('COUNT(b.id)')
            ->from(BookingDoctrine::class, 'b')
            ->where('b.userId = :userId')
            ->setParameter('userId', (string) $userId);
        if ($providerId) {
            $countQb->andWhere('b.providerId = :providerId')
                ->setParameter('providerId', (string) $providerId);
        }
        $this->applyClientDateFilter($countQb, 'b', $dateFrom, $dateTo, $period, $providerLocalDatesById);
        if ($status) {
            $countQb->andWhere('b.status = :status')
                ->setParameter('status', $status);
        }
        $total = (int) $countQb->getQuery()->getSingleScalarResult();

        $pageProviderIds = [];
        foreach ($bookings as $booking) {
            $pageProviderIds[(string) $booking->getProviderId()] = true;
        }
        $filterProviderIds = $this->listProviderIdsForUser($userId);
        $providers = $this->providerRowsByIds(array_values(array_unique(array_merge(array_keys($pageProviderIds), $filterProviderIds))));
        $addresses = $this->addressRowsByProviderIds(array_keys($pageProviderIds));

        return [
            'items' => array_map(
                fn(Booking $booking): array => $this->mapCurrentUserBooking($booking, $providers, $addresses),
                $bookings
            ),
            'filters' => [
                'providers' => $this->mapProviderFilters($filterProviderIds, $providers),
            ],
            'pagination' => $this->pagination($page, $limit, $total),
        ];
    }

    protected function applyClientDateFilter(QueryBuilder $qb, string $alias, ?string $dateFrom, ?string $dateTo, ?string $period, array $providerLocalDatesById): void
    {
        if ($period !== null && $period !== 'all') {
            $groups = [];
            foreach ($providerLocalDatesById as $providerId => $localDate) {
                $providerId = trim((string) $providerId);
                $localDate = trim((string) $localDate);
                if ($providerId === '' || $localDate === '') {
                    continue;
                }
                $groups[$localDate][] = $providerId;
            }

            if ($groups === []) {
                $qb->andWhere('1 = 0');
                return;
            }

            $operator = match ($period) {
                'today' => '=',
                'future' => '>',
                'past' => '<',
                default => null,
            };
            if ($operator === null) {
                return;
            }

            $parts = [];
            $index = 0;
            foreach ($groups as $localDate => $providerIds) {
                $dateParam = 'periodDate' . $index;
                $providersParam = 'periodProviders' . $index;
                $parts[] = sprintf('(%s.providerId IN (:%s) AND %s.bookingDate %s :%s)', $alias, $providersParam, $alias, $operator, $dateParam);
                $qb->setParameter($providersParam, $providerIds)
                    ->setParameter($dateParam, $localDate);
                $index++;
            }

            $qb->andWhere('(' . implode(' OR ', $parts) . ')');
            return;
        }

        if ($dateFrom) {
            $qb->andWhere(sprintf('%s.bookingDate >= :dateFrom', $alias))
                ->setParameter('dateFrom', $dateFrom);
        }
        if ($dateTo) {
            $qb->andWhere(sprintf('%s.bookingDate <= :dateTo', $alias))
                ->setParameter('dateTo', $dateTo);
        }
    }
}
