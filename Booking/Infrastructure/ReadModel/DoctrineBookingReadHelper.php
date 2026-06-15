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


abstract class DoctrineBookingReadHelper
{
    public function __construct(protected readonly EntityManagerInterface $em)
    {
    }

    protected function providerBookingsBaseQuery(string $providerId, ?string $dateFrom, ?string $dateTo, ?string $status, ?EmployeeId $employeeId, ?UserId $clientId): QueryBuilder
    {
        $qb = $this->em->createQueryBuilder()
            ->select('b')
            ->from(BookingDoctrine::class, 'b')
            ->where('b.providerId = :providerId')
            ->setParameter('providerId', $providerId);

        if ($dateFrom) {
            $qb->andWhere('b.bookingDate >= :dateFrom')
                ->setParameter('dateFrom', $dateFrom);
        }
        if ($dateTo) {
            $qb->andWhere('b.bookingDate <= :dateTo')
                ->setParameter('dateTo', $dateTo);
        }
        if ($status) {
            $qb->andWhere('b.status = :status')
                ->setParameter('status', $status);
        }
        if ($employeeId) {
            $qb->andWhere('b.employeeId = :employeeId')
                ->setParameter('employeeId', (string) $employeeId);
        }
        if ($clientId) {
            $qb->andWhere('b.userId = :clientId')
                ->setParameter('clientId', (string) $clientId);
        }

        return $qb;
    }

    protected function providerRowsByIds(array $providerIds): array
    {
        $providerIds = array_values(array_unique(array_filter(array_map('strval', $providerIds))));
        if ($providerIds === []) {
            return [];
        }

        $rows = $this->em->createQueryBuilder()
            ->select('p.id AS id, p.name AS name, p.avatarUrl AS avatarUrl, p.publicSlug AS slug, p.timezone AS timezone')
            ->from(ProviderDoctrine::class, 'p')
            ->where('p.id IN (:ids)')
            ->setParameter('ids', $providerIds)
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            $id = (string) ($row['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $map[$id] = [
                'id' => $id,
                'name' => isset($row['name']) ? (string) $row['name'] : null,
                'avatarUrl' => $row['avatarUrl'] ?? null,
                'avatar' => null,
                'slug' => $row['slug'] ?? null,
                'timezone' => $row['timezone'] ?? null,
            ];
        }

        return $map;
    }

    protected function addressRowsByProviderIds(array $providerIds): array
    {
        $providerIds = array_values(array_unique(array_filter(array_map('strval', $providerIds))));
        if ($providerIds === []) {
            return [];
        }

        $rows = $this->em->createQueryBuilder()
            ->select('p.id AS providerId, l.id AS id, l.name AS name, l.addressLine AS description, l.city AS city, l.street AS street, l.houseNumber AS houseNumber')
            ->from(ProviderLocationDoctrine::class, 'l')
            ->join('l.provider', 'p')
            ->where('p.id IN (:ids)')
            ->setParameter('ids', $providerIds)
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            $providerId = (string) ($row['providerId'] ?? '');
            $id = (string) ($row['id'] ?? '');
            if ($providerId === '' || $id === '') {
                continue;
            }
            $map[$providerId][$id] = [
                'id' => $id,
                'name' => $row['name'] ?? null,
                'description' => $row['description'] ?? null,
                'city' => $row['city'] ?? null,
                'street' => $row['street'] ?? null,
                'houseNumber' => $row['houseNumber'] ?? null,
            ];
        }

        return $map;
    }

    protected function mapProviderFilters(array $providerIds, array $providers): array
    {
        $items = [];
        foreach ($providerIds as $providerId) {
            if (!isset($providers[$providerId])) {
                continue;
            }
            $provider = $providers[$providerId];
            $items[] = [
                'id' => $provider['id'],
                'name' => $provider['name'],
                'avatarUrl' => $provider['avatarUrl'],
                'slug' => $provider['slug'],
            ];
        }

        return $items;
    }

    protected function mapCurrentUserBooking(Booking $booking, array $providerMap, array $addressMap): array
    {
        $providerId = (string) $booking->getProviderId();
        $addressId = (string) $booking->getAddressId();
        $services = $booking->getServicesSnapshot();
        $totals = BookingTotalsView::build($services, $booking->getCustomTotalPrice());

        return [
            'id' => (string) $booking->getId(),
            'providerId' => $providerId,
            'provider' => $providerMap[$providerId] ?? ['id' => $providerId, 'name' => null],
            'addressId' => $addressId,
            'address' => $addressMap[$providerId][$addressId] ?? ['id' => $addressId],
            'status' => $booking->getStatus(),
            'cancelledBy' => $booking->getCancelledBy(),
            'cancelledAt' => $booking->getCancelledAt()?->format(DATE_ATOM),
            'cancelReason' => $booking->getCancelReason(),
            'date' => $booking->getBookingDate(),
            'time' => $booking->getBookingTime(),
            'employeeId' => $booking->getEmployeeId() ? (string) $booking->getEmployeeId() : null,
            'durationMinutes' => $booking->getDurationMinutes(),
            'services' => $services,
            'servicesTotalPrice' => $totals['servicesTotalPrice'],
            'customTotalPrice' => $totals['customTotalPrice'],
            'totalPrice' => $totals['totalPrice'],
            'currency' => $totals['currency'],
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
            'createdAt' => $booking->getCreatedAt()->format('c'),
            'updatedAt' => $booking->getUpdatedAt()->format('c'),
        ];
    }

    protected function mapLegacyBooking(Booking $booking, bool $includeProviderClientFields): array
    {
        $payload = [
            'id' => (string) $booking->getId(),
            'providerId' => (string) $booking->getProviderId(),
            'userId' => $booking->getUserId() ? (string) $booking->getUserId() : null,
            'addressId' => (string) $booking->getAddressId(),
            'employeeId' => $booking->getEmployeeId() ? (string) $booking->getEmployeeId() : null,
            'status' => $booking->getStatus(),
            'cancelledBy' => $booking->getCancelledBy(),
            'cancelledAt' => $booking->getCancelledAt()?->format(DATE_ATOM),
            'cancelReason' => $booking->getCancelReason(),
            'date' => $booking->getBookingDate(),
            'time' => $booking->getBookingTime(),
            'services' => $booking->getServicesSnapshot(),
            'createdAt' => $booking->getCreatedAt()->format('c'),
            'updatedAt' => $booking->getUpdatedAt()->format('c'),
        ];

        if ($includeProviderClientFields) {
            $payload['clientName'] = $booking->getClientName();
            $payload['additionalInfo'] = $booking->getAdditionalInfo();
        } else {
            $payload['userId'] = $booking->getUserId() ? (string) $booking->getUserId() : '';
        }

        return $payload;
    }

    protected function calculateSnapshotTotal(array $services): array
    {
        $total = 0.0;
        $currency = null;
        foreach ($services as $service) {
            if ($currency === null && isset($service['currency'])) {
                $currency = (string) $service['currency'];
            }
            if (is_numeric($service['price'] ?? null)) {
                $total += (float) $service['price'];
            }
        }

        return [$total, $currency];
    }

    protected function employeeMaps(Provider $provider): array
    {
        $employeeMap = [];
        $employeesList = [];
        foreach ($provider->getEmployees() as $employee) {
            if ($employee->isDeleted()) {
                continue;
            }
            $id = (string) $employee->getId();
            $employeeMap[$id] = [
                'id' => $id,
                'name' => $employee->getName(),
                'position' => $employee->getPosition(),
                'avatarUrl' => $employee->getAvatarUrl(),
            ];
            $employeesList[] = [
                'id' => $id,
                'name' => $employee->getName(),
            ];
        }

        return [$employeeMap, $employeesList];
    }

    protected function addressMapFromProvider(Provider $provider): array
    {
        $addressMap = [];
        foreach ($provider->getLocations() as $location) {
            $addressMap[(string) $location->getId()] = [
                'id' => (string) $location->getId(),
                'name' => $location->getName(),
                'description' => $location->getAddressLine(),
                'city' => $location->getCity(),
                'street' => $location->getStreet(),
                'houseNumber' => $location->getHouseNumber(),
                'zip' => $location->getZip(),
                'isPrimary' => $location->isPrimary(),
            ];
        }

        return $addressMap;
    }

    protected function bookingUserIds(array $bookings): array
    {
        $ids = [];
        foreach ($bookings as $booking) {
            if ($booking->getUserId()) {
                $ids[(string) $booking->getUserId()] = true;
            }
        }

        return array_keys($ids);
    }

    protected function userRowsByIds(array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('strval', $userIds))));
        if ($userIds === []) {
            return [];
        }

        $rows = $this->em->createQueryBuilder()
            ->select('u.id AS id, u.email AS email')
            ->from(UserDoctrine::class, 'u')
            ->where('u.id IN (:ids)')
            ->setParameter('ids', $userIds)
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            $id = (string) ($row['id'] ?? '');
            if ($id !== '') {
                $map[$id] = $row;
            }
        }

        return $map;
    }

    protected function profileRowsByUserIds(array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('strval', $userIds))));
        if ($userIds === []) {
            return [];
        }

        $rows = $this->em->createQueryBuilder()
            ->select('p.userId AS userId, p.firstName AS firstName, p.lastName AS lastName, p.phoneNumber AS phoneNumber, p.avatarUrl AS avatarUrl')
            ->from(UserProfileDoctrine::class, 'p')
            ->where('p.userId IN (:ids)')
            ->setParameter('ids', $userIds)
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            $id = (string) ($row['userId'] ?? '');
            if ($id !== '') {
                $map[$id] = $row;
            }
        }

        return $map;
    }

    protected function mapUserPayload(string $userId, array $users, array $profiles): array
    {
        $profile = $profiles[$userId] ?? null;
        $user = $users[$userId] ?? null;
        $name = $userId;
        if ($profile) {
            $profileName = trim((string) ($profile['firstName'] ?? '') . ' ' . (string) ($profile['lastName'] ?? ''));
            if ($profileName !== '') {
                $name = $profileName;
            }
        }
        if ($name === $userId && isset($user['email'])) {
            $name = (string) $user['email'];
        }

        return [
            'id' => $userId,
            'name' => $name,
            'phone' => $profile['phoneNumber'] ?? null,
            'email' => isset($user['email']) ? (string) $user['email'] : null,
            'avatarUrl' => $profile['avatarUrl'] ?? null,
        ];
    }

    protected function mapProviderBooking(Booking $booking, array $users, array $profiles, array $addressMap, array $employeeMap): array
    {
        $userId = $booking->getUserId() ? (string) $booking->getUserId() : null;
        $addressId = (string) $booking->getAddressId();
        $bookingEmployeeId = $booking->getEmployeeId() ? (string) $booking->getEmployeeId() : null;
        $services = $booking->getServicesSnapshot();
        $totals = BookingTotalsView::build($services, $booking->getCustomTotalPrice());

        return [
            'id' => (string) $booking->getId(),
            'date' => $booking->getBookingDate(),
            'time' => $booking->getBookingTime(),
            'durationMinutes' => $booking->getDurationMinutes(),
            'status' => $booking->getStatus(),
            'cancelledBy' => $booking->getCancelledBy(),
            'cancelledAt' => $booking->getCancelledAt()?->format(DATE_ATOM),
            'cancelReason' => $booking->getCancelReason(),
            'services' => $services,
            'servicesTotalPrice' => $totals['servicesTotalPrice'],
            'customTotalPrice' => $totals['customTotalPrice'],
            'totalPrice' => $totals['totalPrice'],
            'currency' => $totals['currency'],
            'address' => $addressMap[$addressId] ?? ['id' => $addressId],
            'employee' => $bookingEmployeeId !== null
                ? ($employeeMap[$bookingEmployeeId] ?? ['id' => $bookingEmployeeId])
                : null,
            'user' => $userId !== null ? $this->mapUserPayload($userId, $users, $profiles) : [
                'id' => null,
                'name' => $booking->getClientName(),
                'phone' => null,
                'email' => null,
                'avatarUrl' => null,
            ],
            'payment' => [
                'required' => $booking->isPaymentRequired(),
                'type' => $booking->getPaymentType(),
                'status' => $booking->getPaymentStatus(),
                'amountDue' => $booking->getPaymentAmountDue(),
                'currency' => $booking->getCurrency(),
                'paidAt' => $booking->getPaidAt()?->format(DATE_ATOM),
                'paymentProvider' => $booking->getPaymentProvider(),
            ],
            'source' => $booking->getSource(),
            'clientName' => $booking->getClientName(),
            'additionalInfo' => $booking->getAdditionalInfo(),
        ];
    }

    protected function employeePayloadForBooking(Provider $provider, Booking $booking): ?array
    {
        $employeeId = $booking->getEmployeeId() ? (string) $booking->getEmployeeId() : null;
        if ($employeeId === null) {
            return null;
        }

        foreach ($provider->getEmployees() as $employee) {
            if ((string) $employee->getId() !== $employeeId || $employee->isDeleted()) {
                continue;
            }

            return [
                'id' => $employeeId,
                'name' => $employee->getName(),
                'position' => $employee->getPosition(),
                'avatarUrl' => $employee->getAvatarUrl(),
            ];
        }

        return ['id' => $employeeId];
    }

    protected function addressPayloadForBooking(Provider $provider, Booking $booking): array
    {
        $addressId = (string) $booking->getAddressId();
        foreach ($provider->getLocations() as $location) {
            if ((string) $location->getId() !== $addressId) {
                continue;
            }

            return [
                'id' => (string) $location->getId(),
                'name' => $location->getName(),
                'description' => $location->getAddressLine(),
                'city' => $location->getCity(),
                'street' => $location->getStreet(),
                'houseNumber' => $location->getHouseNumber(),
            ];
        }

        return ['id' => $addressId];
    }

    protected function enrichServicesSnapshot(array $services, Provider $provider): array
    {
        $providerServices = [];
        $categoryNames = [];
        foreach ($provider->getServices() as $service) {
            $serviceId = (string) $service->getId();
            $providerServices[$serviceId] = $service;
            if ($service->getType() === ServiceType::CATEGORY) {
                $categoryNames[$serviceId] = $service->getName();
            }
        }

        $employeeServiceIds = [];
        foreach ($services as $service) {
            if (!is_array($service)) {
                continue;
            }
            $employeeServiceId = isset($service['employeeServiceId']) && is_string($service['employeeServiceId']) && $service['employeeServiceId'] !== ''
                ? $service['employeeServiceId']
                : null;
            if ($employeeServiceId !== null) {
                $employeeServiceIds[$employeeServiceId] = true;
            }
        }
        $employeeCategories = $this->employeeServiceCategories(array_keys($employeeServiceIds));

        foreach ($services as $index => $service) {
            if (!is_array($service)) {
                continue;
            }

            $categoryId = isset($service['categoryId']) && is_string($service['categoryId']) && $service['categoryId'] !== ''
                ? $service['categoryId']
                : null;
            $category = isset($service['category']) && is_string($service['category']) && trim($service['category']) !== ''
                ? trim($service['category'])
                : null;
            $serviceId = isset($service['id']) && is_string($service['id']) && $service['id'] !== '' ? $service['id'] : null;
            $employeeServiceId = isset($service['employeeServiceId']) && is_string($service['employeeServiceId']) && $service['employeeServiceId'] !== ''
                ? $service['employeeServiceId']
                : null;

            if ($category === null && $serviceId !== null && isset($providerServices[$serviceId])) {
                $providerService = $providerServices[$serviceId];
                $parentId = $providerService->getParentId();
                if ($parentId !== null) {
                    $categoryId ??= (string) $parentId;
                    $category = $categoryNames[(string) $parentId] ?? null;
                }
            }

            if (($category === null || $categoryId === null) && $employeeServiceId !== null && isset($employeeCategories[$employeeServiceId])) {
                $categoryId ??= $employeeCategories[$employeeServiceId];
                $category ??= $categoryNames[$employeeCategories[$employeeServiceId]] ?? null;
            }

            $services[$index]['categoryId'] = $categoryId;
            $services[$index]['category'] = $category;
        }

        return $services;
    }

    protected function employeeServiceCategories(array $employeeServiceIds): array
    {
        $employeeServiceIds = array_values(array_unique(array_filter(array_map('strval', $employeeServiceIds))));
        if ($employeeServiceIds === []) {
            return [];
        }

        $rows = $this->em->createQueryBuilder()
            ->select('s.id AS id, s.categoryId AS categoryId')
            ->from(ProviderEmployeeServiceDoctrine::class, 's')
            ->where('s.id IN (:ids)')
            ->setParameter('ids', $employeeServiceIds)
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            $id = (string) ($row['id'] ?? '');
            $categoryId = (string) ($row['categoryId'] ?? '');
            if ($id !== '' && $categoryId !== '') {
                $map[$id] = $categoryId;
            }
        }

        return $map;
    }

    protected function paymentRowByBookingId(string $bookingId): ?array
    {
        $rows = $this->em->createQueryBuilder()
            ->select('p.amountMinor AS amountMinor, p.providerAmountMinor AS providerAmountMinor, p.platformFeeMinor AS platformFeeMinor, p.platformFeePercentSnapshot AS platformFeePercentSnapshot, p.status AS status, p.updatedAt AS updatedAt')
            ->from(PaymentDoctrine::class, 'p')
            ->where('p.bookingId = :bookingId')
            ->setParameter('bookingId', $bookingId)
            ->setMaxResults(1)
            ->getQuery()
            ->getArrayResult();

        return $rows[0] ?? null;
    }

    protected function commissionPercent(string $providerId): float
    {
        $rows = $this->em->createQueryBuilder()
            ->select('t.commissionPercent AS commissionPercent')
            ->from(ProviderCommercialTermsDoctrine::class, 't')
            ->where('t.providerId = :providerId')
            ->setParameter('providerId', $providerId)
            ->setMaxResults(1)
            ->getQuery()
            ->getArrayResult();

        return isset($rows[0]['commissionPercent'])
            ? (float) $rows[0]['commissionPercent']
            : ProviderCommercialTerms::DEFAULT_COMMISSION_PERCENT;
    }

    protected function minorToMajor(?int $amountMinor): ?float
    {
        if ($amountMinor === null) {
            return null;
        }

        return round($amountMinor / 100, 2);
    }

    protected function pagination(int $page, int $limit, int $total): array
    {
        return [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => (int) ceil($total / max(1, $limit)),
        ];
    }
}
