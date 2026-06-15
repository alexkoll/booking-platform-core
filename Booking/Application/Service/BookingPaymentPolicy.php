<?php

namespace App\Booking\Application\Service;

final class BookingPaymentPolicy
{
    public function __construct(
        public readonly bool $required,
        public readonly string $paymentType,
        public readonly ?string $depositMode,
        public readonly ?float $depositValue,
        public readonly ?float $amountDue,
        public readonly ?string $currency,
        public readonly string $chargeMode,
    ) {
    }
}
