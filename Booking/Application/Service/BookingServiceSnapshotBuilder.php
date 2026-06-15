<?php

namespace App\Booking\Application\Service;

use App\Booking\Domain\Exception\ServiceNotAvailableException;
use App\Provider\Domain\Entity\Provider;
use App\Provider\Domain\Entity\ProviderEmployeeService;
use App\Provider\Domain\Entity\ProviderService;
use App\Provider\Domain\Entity\ServiceType;
use App\Provider\Domain\Repository\ProviderEmployeeServiceRepository;
use App\Provider\Domain\ValueObject\EmployeeId;
use App\Provider\Domain\ValueObject\ProviderLocationId;
use App\Provider\Domain\ValueObject\ServiceId;

final class BookingServiceSnapshotBuilder
{
    public function __construct(
        private readonly ProviderEmployeeServiceRepository $employeeServices,
    ) {
    }

    public function buildOnline(Provider $provider, ProviderLocationId $addressId, array $employeeServiceIds, ?EmployeeId $employeeId): array
    {
        $requested = array_values(array_unique(array_map('strval', $employeeServiceIds)));
        $snapshot = [];
        $categoryNames = $this->buildProviderCategoryNameMap($provider);

        foreach ($requested as $serviceId) {
            if ($serviceId === '') {
                continue;
            }

            $service = $this->findEmployeeService($serviceId);
            if (!$service instanceof ProviderEmployeeService) {
                $providerService = $this->findOnlineProviderServiceById($provider, $serviceId, $addressId);
                if (!$providerService) {
                    throw new ServiceNotAvailableException('Service not found');
                }

                $snapshot[] = (new BookingServiceSnapshotItem(
                    $serviceId,
                    $providerService['name'],
                    $providerService['description'],
                    $providerService['categoryId'],
                    $providerService['category'],
                    (float) $providerService['price'],
                    $providerService['currency'],
                    (int) $providerService['duration'],
                    $serviceId,
                    (string) $addressId
                ))->toArray();
                continue;
            }

            if ($service->getType() !== 'service') {
                throw new ServiceNotAvailableException('Service must be a service type');
            }
            if (!$service->isActive()) {
                throw new ServiceNotAvailableException('Service is inactive');
            }
            if ((string) $service->getProviderId() !== (string) $provider->getId()) {
                throw new ServiceNotAvailableException('Service not found');
            }
            if ($employeeId && (string) $service->getEmployeeId() !== (string) $employeeId) {
                throw new ServiceNotAvailableException('Service not found');
            }
            $locationId = $service->getLocationId();
            if ($locationId && (string) $locationId !== (string) $addressId) {
                throw new ServiceNotAvailableException('Service not available at this address');
            }

            $duration = $service->getDurationMinutes();
            if ($duration <= 0) {
                throw new ServiceNotAvailableException('Service duration must be positive');
            }

            $categoryId = $service->getCategoryId() ? (string) $service->getCategoryId() : null;
            $snapshot[] = (new BookingServiceSnapshotItem(
                (string) $service->getId(),
                $service->getName(),
                $service->getDescription(),
                $categoryId,
                $categoryId ? ($categoryNames[$categoryId] ?? null) : null,
                (float) $service->getPrice(),
                $service->getCurrency(),
                $duration,
                (string) $service->getId(),
                (string) $addressId,
                null,
                $service->getType()
            ))->toArray();
        }

        if (!$snapshot) {
            throw new ServiceNotAvailableException('No valid services selected');
        }

        return $snapshot;
    }

    public function buildOffline(Provider $provider, ProviderLocationId $addressId, array $serviceIds, ?EmployeeId $employeeId): array
    {
        $requested = array_values(array_unique(array_map('strval', $serviceIds)));
        $snapshot = [];
        $categoryNames = $this->buildProviderCategoryNameMap($provider);

        foreach ($requested as $serviceId) {
            if ($serviceId === '') {
                continue;
            }

            $service = $this->findEmployeeService($serviceId);
            if (!$service instanceof ProviderEmployeeService) {
                $providerService = $this->findOfflineProviderServiceById($provider, $serviceId);
                if (!$providerService) {
                    throw new ServiceNotAvailableException('Service not found');
                }
                $snapshot[] = $this->mapOfflineProviderService($providerService, $addressId, $categoryNames);
                continue;
            }

            $snapshot[] = [
                'id' => (string) $service->getId(),
                'name' => $service->getName(),
                'categoryId' => $service->getCategoryId() ? (string) $service->getCategoryId() : null,
                'category' => $service->getCategoryId() ? ($categoryNames[(string) $service->getCategoryId()] ?? null) : null,
                'durationMinutes' => $service->getDurationMinutes(),
                'price' => $service->getPrice(),
                'currency' => $service->getCurrency(),
                'employeeServiceId' => (string) $service->getId(),
                'employeeId' => $employeeId ? (string) $employeeId : null,
            ];
        }

        return $snapshot;
    }

    public function calculateDurationMinutes(array $snapshot): int
    {
        $total = 0;
        foreach ($snapshot as $item) {
            $duration = $item['durationMinutes'] ?? null;
            if (!is_int($duration) && !is_float($duration) && !is_string($duration)) {
                throw new ServiceNotAvailableException('Service duration is required');
            }
            $duration = (int) $duration;
            if ($duration <= 0) {
                throw new ServiceNotAvailableException('Service duration must be positive');
            }
            $total += $duration;
        }

        return $total;
    }

    public function calculateOfflineDurationMinutes(array $snapshot): int
    {
        return array_reduce(
            $snapshot,
            static fn(int $carry, array $item) => $carry + (int) ($item['durationMinutes'] ?? 0),
            0
        );
    }

    public function calculateServicesTotalPrice(array $snapshot): float
    {
        return array_reduce(
            $snapshot,
            static fn(float $carry, array $item): float => $carry + (is_numeric($item['price'] ?? null) ? (float) $item['price'] : 0.0),
            0.0
        );
    }

    private function findEmployeeService(string $serviceId): ?ProviderEmployeeService
    {
        try {
            $service = $this->employeeServices->findById(ServiceId::fromString($serviceId));
        } catch (\Throwable) {
            return null;
        }

        return $service instanceof ProviderEmployeeService ? $service : null;
    }

    private function findOnlineProviderServiceById(Provider $provider, string $serviceId, ProviderLocationId $addressId): ?array
    {
        $categoryNames = $this->buildProviderCategoryNameMap($provider);

        foreach ($provider->getServices() as $service) {
            if ((string) $service->getId() !== (string) $serviceId) {
                continue;
            }
            if ($service->getType() !== ServiceType::SERVICE || $service->isArchived()) {
                return null;
            }

            $priceEntity = $service->getPrice();
            if (!$priceEntity || !$priceEntity->isActive()) {
                return null;
            }

            $locationId = $service->getLocationId();
            if ($locationId && (string) $locationId !== (string) $addressId) {
                return null;
            }

            $duration = $priceEntity->getDurationMinutes();
            if ($duration <= 0) {
                return null;
            }

            return [
                'id' => (string) $service->getId(),
                'categoryId' => $service->getParentId() ? (string) $service->getParentId() : null,
                'category' => $service->getParentId() ? ($categoryNames[(string) $service->getParentId()] ?? null) : null,
                'name' => $service->getName(),
                'description' => $service->getDescription(),
                'price' => $priceEntity->getPrice(),
                'currency' => $priceEntity->getCurrency(),
                'duration' => $duration,
            ];
        }

        return null;
    }

    private function findOfflineProviderServiceById(Provider $provider, string $id): ?ProviderService
    {
        foreach ($provider->getServices() as $service) {
            if ((string) $service->getId() === $id) {
                return $service;
            }
        }

        return null;
    }

    /**
     * @param array<string, string> $categoryNames
     */
    private function mapOfflineProviderService(ProviderService $service, ProviderLocationId $addressId, array $categoryNames): array
    {
        if ($service->getType() !== ServiceType::SERVICE) {
            return [];
        }
        $locationId = $service->getLocationId();
        if ($locationId && (string) $locationId !== (string) $addressId) {
            return [];
        }
        $price = $service->getPrice()?->getPrice() ?? 0;
        $duration = $service->getPrice()?->getDurationMinutes() ?? 0;
        $currency = $service->getPrice()?->getCurrency();

        return [
            'id' => (string) $service->getId(),
            'name' => $service->getName(),
            'categoryId' => $service->getParentId() ? (string) $service->getParentId() : null,
            'category' => $service->getParentId() ? ($categoryNames[(string) $service->getParentId()] ?? null) : null,
            'durationMinutes' => $duration,
            'price' => $price,
            'currency' => $currency,
            'employeeServiceId' => null,
            'employeeId' => null,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function buildProviderCategoryNameMap(Provider $provider): array
    {
        $map = [];
        foreach ($provider->getServices() as $service) {
            if ($service->getType() !== ServiceType::CATEGORY) {
                continue;
            }
            $map[(string) $service->getId()] = $service->getName();
        }

        return $map;
    }
}
