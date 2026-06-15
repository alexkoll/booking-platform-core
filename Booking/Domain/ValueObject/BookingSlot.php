<?php

namespace App\Booking\Domain\ValueObject;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final readonly class BookingSlot
{
    private string $date;
    private string $time;
    private int $durationMinutes;

    private function __construct(string $date, string $time, int $durationMinutes)
    {
        $dateObj = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException('Booking date must be in YYYY-MM-DD format');
        }

        $timeObj = DateTimeImmutable::createFromFormat('!H:i:s', $time)
            ?: DateTimeImmutable::createFromFormat('!H:i', $time);
        if (!$timeObj) {
            throw new InvalidArgumentException('Booking time must be in HH:MM or HH:MM:SS format');
        }

        if ($durationMinutes <= 0) {
            throw new InvalidArgumentException('Booking duration must be positive');
        }

        $this->date = $date;
        $this->time = $timeObj->format('H:i:s');
        $this->durationMinutes = $durationMinutes;
    }

    public static function fromDateTimeParts(string $date, string $time, int $durationMinutes): self
    {
        return new self($date, $time, $durationMinutes);
    }

    public function date(): string
    {
        return $this->date;
    }

    public function time(): string
    {
        return $this->time;
    }

    public function durationMinutes(): int
    {
        return $this->durationMinutes;
    }

    public function startsAt(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->date . ' ' . $this->time);
    }

    public function startsAtIn(DateTimeZone $timezone): DateTimeImmutable
    {
        return new DateTimeImmutable($this->date . ' ' . $this->time, $timezone);
    }

    public function endsAt(): DateTimeImmutable
    {
        return $this->startsAt()->modify('+' . $this->durationMinutes . ' minutes');
    }

    public function endsAtIn(DateTimeZone $timezone): DateTimeImmutable
    {
        return $this->startsAtIn($timezone)->modify('+' . $this->durationMinutes . ' minutes');
    }
}
