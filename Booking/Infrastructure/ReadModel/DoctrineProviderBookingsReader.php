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


final class DoctrineProviderBookingsReader extends DoctrineBookingReadHelper implements ProviderBookingsReader
{
    public function listProviderBookings(
        Provider $provider,
        int $page,
        int $limit,
        ?string $dateFrom,
        ?string $dateTo,
        ?string $status,
        ?EmployeeId $employeeId,
        ?UserId $clientId
    ): array {
        $page = max(1, $page);
        $limit = max(1, min(50, $limit));
        $offset = ($page - 1) * $limit;
        $providerId = (string) $provider->getId();

        $qb = $this->providerBookingsBaseQuery($providerId, $dateFrom, $dateTo, $status, $employeeId, $clientId);
        $qb->orderBy('b.bookingDate', 'DESC')
            ->addOrderBy('b.bookingTime', 'DESC')
            ->addOrderBy('b.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        /** @var BookingDoctrine[] $entities */
        $entities = $qb->getQuery()->getResult();
        $bookings = array_map(static fn(BookingDoctrine $entity): Booking => $entity->toDomain(), $entities);

        $countQb = $this->providerBookingsBaseQuery($providerId, $dateFrom, $dateTo, $status, $employeeId, $clientId)
            ->select('COUNT(b.id)');
        $total = (int) $countQb->getQuery()->getSingleScalarResult();

        [$employeeMap, $employeesList] = $this->employeeMaps($provider);
        $addressMap = $this->addressMapFromProvider($provider);
        $userIds = $this->bookingUserIds($bookings);
        $users = $this->userRowsByIds($userIds);
        $profiles = $this->profileRowsByUserIds($userIds);
        $clientMap = [];

        $items = array_map(
            function (Booking $booking) use ($users, $profiles, $addressMap, $employeeMap, &$clientMap): array {
                $payload = $this->mapProviderBooking($booking, $users, $profiles, $addressMap, $employeeMap);
                $userId = $booking->getUserId() ? (string) $booking->getUserId() : null;
                if ($userId !== null) {
                    $clientMap[$userId] = [
                        'id' => $userId,
                        'name' => $payload['user']['name'] ?? $userId,
                    ];
                }

                return $payload;
            },
            $bookings
        );

        return [
            'providerType' => $provider->getType(),
            'employees' => $employeesList,
            'items' => $items,
            'clients' => array_values($clientMap),
            'pagination' => $this->pagination($page, $limit, $total),
        ];
    }

    public function listEmployeeBookings(
        Provider $provider,
        EmployeeId $employeeId,
        int $page,
        int $limit,
        ?string $dateFrom,
        ?string $dateTo,
        ?string $status,
        ?UserId $clientId
    ): array {
        $payload = $this->listProviderBookings($provider, $page, $limit, $dateFrom, $dateTo, $status, $employeeId, $clientId);

        return [
            'items' => array_map(static fn(array $item): array => [
                'id' => $item['id'],
                'date' => $item['date'],
                'time' => $item['time'],
                'durationMinutes' => $item['durationMinutes'],
                'status' => $item['status'],
                'cancelledBy' => $item['cancelledBy'],
                'cancelledAt' => $item['cancelledAt'],
                'cancelReason' => $item['cancelReason'],
                'services' => $item['services'],
                'servicesTotalPrice' => $item['servicesTotalPrice'],
                'customTotalPrice' => $item['customTotalPrice'],
                'totalPrice' => $item['totalPrice'],
                'currency' => $item['currency'],
                'address' => $item['address'],
                'user' => $item['user'],
            ], $payload['items']),
            'clients' => $payload['clients'],
            'pagination' => $payload['pagination'],
        ];
    }
}
