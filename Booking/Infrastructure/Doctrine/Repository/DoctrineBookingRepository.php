<?php

namespace App\Booking\Infrastructure\Doctrine\Repository;

use App\Booking\Domain\Entity\Booking;
use App\Booking\Domain\Exception\BookingSlotUnavailableException;
use App\Booking\Domain\Repository\BookingRepository;
use App\Booking\Domain\ValueObject\BookingId;
use App\Booking\Infrastructure\Doctrine\Entity\BookingDoctrine;
use App\Provider\Domain\ValueObject\EmployeeId;
use App\Provider\Domain\ValueObject\ProviderId;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Throwable;

final class DoctrineBookingRepository implements BookingRepository
{
    private const ACTIVE_OVERLAP_CONSTRAINT = 'bookings_no_active_overlap';

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function add(Booking $booking): void
    {
        $existing = $this->em->find(BookingDoctrine::class, (string) $booking->getId());
        if ($existing) {
            $existing->updateFromDomain($booking);
        } else {
            $existing = BookingDoctrine::fromDomain($booking);
            $this->em->persist($existing);
        }

        try {
            $this->em->flush();
        } catch (Throwable $exception) {
            if ($this->isActiveOverlapConstraintViolation($exception)) {
                throw new BookingSlotUnavailableException($exception);
            }

            throw $exception;
        }
    }

    public function byId(BookingId $id): Booking
    {
        $entity = $this->em->find(BookingDoctrine::class, (string) $id);
        if (!$entity) {
            throw new RuntimeException('Booking not found');
        }

        return $entity->toDomain();
    }

    public function hasBookingsForEmployee(EmployeeId $employeeId): bool
    {
        $qb = $this->em->createQueryBuilder()
            ->select('COUNT(b.id)')
            ->from(BookingDoctrine::class, 'b')
            ->where('b.employeeId = :employeeId')
            ->setParameter('employeeId', (string) $employeeId);

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    public function findExpiredPaymentBookings(\DateTimeImmutable $now, int $limit = 200): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('b')
            ->from(BookingDoctrine::class, 'b')
            ->where('b.paymentRequired = :required')
            ->andWhere('b.paymentStatus = :status')
            ->andWhere('b.paymentDeadlineAt IS NOT NULL')
            ->andWhere('b.paymentDeadlineAt < :now')
            ->setParameter('required', true)
            ->setParameter('status', \App\Booking\Domain\Entity\Booking::PAYMENT_STATUS_PENDING)
            ->setParameter('now', $now)
            ->orderBy('b.paymentDeadlineAt', 'ASC')
            ->setMaxResults($limit);

        $items = $qb->getQuery()->getResult();

        return array_map(static fn(BookingDoctrine $item) => $item->toDomain(), $items);
    }

    public function findByProviderAddressAndDate(ProviderId $providerId, \App\Provider\Domain\ValueObject\ProviderLocationId $addressId, string $date, ?EmployeeId $employeeId = null): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('b')
            ->from(BookingDoctrine::class, 'b')
            ->where('b.providerId = :providerId')
            ->andWhere('b.addressId = :addressId')
            ->andWhere('b.bookingDate = :date')
            ->setParameter('providerId', (string) $providerId)
            ->setParameter('addressId', (string) $addressId)
            ->setParameter('date', new \DateTimeImmutable($date))
            ->orderBy('b.bookingTime', 'ASC');

        if ($employeeId) {
            $qb->andWhere('b.employeeId = :employeeId')
                ->setParameter('employeeId', (string) $employeeId);
        }

        /** @var BookingDoctrine[] $items */
        $items = $qb->getQuery()->getResult();

        return array_map(static fn(BookingDoctrine $item) => $item->toDomain(), $items);
    }

    private function isActiveOverlapConstraintViolation(Throwable $exception): bool
    {
        do {
            if ($exception instanceof DriverException
                && $exception->getSQLState() === '23P01'
                && str_contains($exception->getMessage(), self::ACTIVE_OVERLAP_CONSTRAINT)
            ) {
                return true;
            }

            $exception = $exception->getPrevious();
        } while ($exception instanceof Throwable);

        return false;
    }
}
