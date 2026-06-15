<?php

namespace App\Booking\Application\Command\UpdateBookingStatus;

use Symfony\Component\Validator\Constraints as Assert;

final class UpdateBookingStatusCommand
{
    public function __construct(
        #[Assert\NotBlank]
        public readonly string $bookingId,
        #[Assert\NotBlank]
        #[Assert\Choice(['confirmed', 'cancelled', 'completed', 'no_show'])]
        public readonly string $status
    ) {
    }
}
