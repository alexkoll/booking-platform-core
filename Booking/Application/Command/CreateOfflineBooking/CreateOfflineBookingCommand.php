<?php

namespace App\Booking\Application\Command\CreateOfflineBooking;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateOfflineBookingCommand
{
    public function __construct(
        #[Assert\NotBlank]
        public readonly string $providerId,
        #[Assert\NotBlank]
        public readonly string $addressId,
        public readonly ?string $employeeId,
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '/^\d{4}-\d{2}-\d{2}$/')]
        public readonly string $date,
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '/^\d{2}:\d{2}(:\d{2})?$/')]
        public readonly string $time,
        #[Assert\NotNull]
        #[Assert\All([
            new Assert\NotBlank(),
            new Assert\Regex(pattern: '/^\d{2}:\d{2}$/')
        ])]
        public readonly array $slots,
        #[Assert\NotNull]
        #[Assert\Count(min: 1)]
        #[Assert\All([
            new Assert\NotBlank()
        ])]
        public readonly array $serviceIds,
        #[Assert\NotBlank]
        public readonly string $clientName,
        public readonly ?string $additionalInfo = null,
        public readonly ?float $totalPrice = null
    ) {
    }
}
