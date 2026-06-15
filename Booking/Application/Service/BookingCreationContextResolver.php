<?php

namespace App\Booking\Application\Service;

use App\Booking\Domain\Exception\EmployeeNotAvailableException;
use App\Provider\Domain\Entity\Employee;
use App\Provider\Domain\Entity\Provider;
use App\Provider\Domain\Repository\EmployeeRepository;
use App\Provider\Domain\Repository\ProviderEmployeeLocationRepository;
use App\Provider\Domain\Repository\ProviderRepository;
use App\Provider\Domain\ValueObject\EmployeeId;
use App\Provider\Domain\ValueObject\ProviderId;
use App\Provider\Domain\ValueObject\ProviderLocationId;
use RuntimeException;

final class BookingCreationContextResolver
{
    public function __construct(
        private readonly ProviderRepository $providers,
        private readonly EmployeeRepository $employees,
        private readonly ProviderEmployeeLocationRepository $employeeLocations,
    ) {
    }

    public function resolveOnline(ProviderId $providerId, ProviderLocationId $addressId, ?EmployeeId $employeeId): BookingCreationContext
    {
        try {
            $provider = $this->providers->byId($providerId);
        } catch (\Throwable) {
            throw new RuntimeException('Provider not found');
        }

        return $this->resolve($provider, $addressId, $employeeId, true);
    }

    public function resolveOffline(ProviderId $providerId, ProviderLocationId $addressId, ?EmployeeId $employeeId): BookingCreationContext
    {
        try {
            $provider = $this->providers->byId($providerId);
        } catch (\Throwable) {
            throw new RuntimeException('Provider not found');
        }

        return $this->resolve($provider, $addressId, $employeeId, false);
    }

    private function resolve(Provider $provider, ProviderLocationId $addressId, ?EmployeeId $employeeId, bool $online): BookingCreationContext
    {
        if ($online) {
            if ($provider->getType() === Provider::TYPE_COMPANY && $employeeId === null) {
                throw new EmployeeNotAvailableException('Employee is required');
            }

            $this->assertAddressExists($provider, $addressId);
            $employee = $employeeId ? $this->resolveEmployee($provider, $addressId, $employeeId, true) : null;

            return new BookingCreationContext($provider, $addressId, $employeeId, $employee);
        }

        $employee = null;
        if ($employeeId) {
            $employee = $this->resolveEmployee($provider, $addressId, $employeeId, false);
        } elseif ($provider->getType() === Provider::TYPE_COMPANY) {
            throw new EmployeeNotAvailableException('Employee is required');
        }

        $this->assertAddressExists($provider, $addressId);

        return new BookingCreationContext($provider, $addressId, $employeeId, $employee);
    }

    private function assertAddressExists(Provider $provider, ProviderLocationId $addressId): void
    {
        $addressExists = false;
        foreach ($provider->getLocations() as $location) {
            if ((string) $location->getId() === (string) $addressId) {
                $addressExists = true;
                break;
            }
        }
        if (!$addressExists) {
            throw new RuntimeException('Address not found');
        }
    }

    private function resolveEmployee(Provider $provider, ProviderLocationId $addressId, EmployeeId $employeeId, bool $online): Employee
    {
        try {
            $employee = $this->employees->byId($employeeId);
        } catch (\Throwable) {
            throw new EmployeeNotAvailableException($online ? 'Employee not found' : 'Employee not available');
        }

        if ($employee->isDeleted() || $employee->isInvited()) {
            throw new EmployeeNotAvailableException('Employee not available');
        }
        if ((string) $employee->getProviderId() !== (string) $provider->getId()) {
            throw new EmployeeNotAvailableException($online ? 'Employee not found' : 'Employee not available');
        }

        $employeeLocationItems = $this->employeeLocations->findByEmployeeId($employeeId);
        if ($employeeLocationItems) {
            $allowedLocations = array_map(
                static fn($item) => (string) $item->getLocationId(),
                $employeeLocationItems
            );
            if (!in_array((string) $addressId, $allowedLocations, true)) {
                throw new EmployeeNotAvailableException('Employee not available at this address');
            }
        }

        return $employee;
    }
}
