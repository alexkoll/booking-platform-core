<?php

namespace App\Booking\Domain\ValueObject;

use InvalidArgumentException;

final class BookingSource
{
    public const ONLINE = 'online';
    public const OFFLINE = 'offline';

    public static function assertValid(string $source): void
    {
        if (!in_array($source, [self::ONLINE, self::OFFLINE], true)) {
            throw new InvalidArgumentException('Invalid booking source');
        }
    }
}
