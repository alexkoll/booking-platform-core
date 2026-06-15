<?php

namespace App\Booking\Domain\Exception;

use RuntimeException;
use Throwable;

final class BookingSlotUnavailableException extends RuntimeException
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct('Slot unavailable', 0, $previous);
    }
}
