<?php

namespace App\Booking\Domain\ValueObject;

use InvalidArgumentException;

final class BookingStatus
{
    public const PENDING = 'pending';
    public const NEW = self::PENDING;
    public const CONFIRMED = 'confirmed';
    public const CANCELLED = 'cancelled';
    public const COMPLETED = 'completed';
    public const NO_SHOW = 'no_show';
    public const EXPIRED = 'expired';

    public static function assertValid(string $status): void
    {
        $allowed = [
            self::PENDING,
            self::CONFIRMED,
            self::CANCELLED,
            self::COMPLETED,
            self::NO_SHOW,
            self::EXPIRED,
        ];

        if (!in_array($status, $allowed, true)) {
            throw new InvalidArgumentException('Invalid booking status');
        }
    }
}
