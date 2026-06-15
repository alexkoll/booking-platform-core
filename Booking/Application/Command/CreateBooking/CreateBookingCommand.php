<?php

namespace App\Booking\Application\Command\CreateBooking;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateBookingCommand
{
    public function __construct(
        #[Assert\NotBlank]
        public readonly string $providerId,
        #[Assert\NotBlank]
        public readonly string $addressId,
        #[Assert\NotBlank]
        public readonly string $userId,
        public readonly ?string $clientName = null,
        public readonly ?string $additionalInfo = null,
        public readonly ?string $employeeId,
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '/^\d{4}-\d{2}-\d{2}$/')]
        public readonly string $date,
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '/^\d{2}:\d{2}(:\d{2})?$/')]
        public readonly string $time,
        #[Assert\NotNull]
        #[Assert\Count(min: 1)]
        #[Assert\All([
            new Assert\NotBlank()
        ])]
        public readonly array $employeeServiceIds,
        #[Assert\All([
            new Assert\Type('string'),
            new Assert\NotBlank()
        ])]
        public readonly array $promoIds = [],
        #[Assert\IsTrue(message: 'booking_terms_not_accepted')]
        public readonly bool $termsAccepted = false
    ) {
    }
}
