<?php

namespace App\Booking\Domain\Exception;

use RuntimeException;

final class BookingTermsNotAcceptedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('booking_terms_not_accepted');
    }
}
