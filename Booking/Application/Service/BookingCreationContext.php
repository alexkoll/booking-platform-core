<?php

namespace App\Booking\Application\Service;

use App\Provider\Domain\Entity\Employee;
use App\Provider\Domain\Entity\Provider;
use App\Provider\Domain\ValueObject\EmployeeId;
use App\Provider\Domain\ValueObject\ProviderLocationId;

final class BookingCreationContext
{
    public function __construct(
        public readonly Provider $provider,
        public readonly ProviderLocationId $addressId,
        public readonly ?EmployeeId $employeeId,
        public readonly ?Employee $employee,
    ) {
    }
}
