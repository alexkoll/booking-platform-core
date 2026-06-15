<?php

namespace App\Booking\Domain\ValueObject;

use InvalidArgumentException;
use Symfony\Component\Uid\Ulid;

final class BookingId
{
    private Ulid $value;

    private function __construct(string $value)
    {
        $this->value = self::normalize($value);
    }

    public static function generate(): self
    {
        return new self((string) new Ulid());
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function equals(BookingId $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }

    public function toUlid(): Ulid
    {
        return $this->value;
    }

    private static function normalize(string $value): Ulid
    {
        try {
            return Ulid::fromString(trim($value));
        } catch (\Throwable $e) {
            throw new InvalidArgumentException('BookingId must be a valid ULID', 0, $e);
        }
    }
}
