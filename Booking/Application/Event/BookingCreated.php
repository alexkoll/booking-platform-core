<?php

namespace App\Booking\Application\Event;

final class BookingCreated
{
    public function __construct(
        private readonly string $bookingId,
        private readonly string $providerId,
        private readonly ?string $userId,
        private readonly string $addressId,
        private readonly string $date,
        private readonly string $time,
        private readonly int $durationMinutes,
        private readonly string $status,
        private readonly string $clientName,
        private readonly ?string $additionalInfo,
        private readonly string $source
    ) {
    }

    public function getBookingId(): string
    {
        return $this->bookingId;
    }

    public function getProviderId(): string
    {
        return $this->providerId;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function getAddressId(): string
    {
        return $this->addressId;
    }

    public function getDate(): string
    {
        return $this->date;
    }

    public function getTime(): string
    {
        return $this->time;
    }

    public function getDurationMinutes(): int
    {
        return $this->durationMinutes;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getClientName(): string
    {
        return $this->clientName;
    }

    public function getAdditionalInfo(): ?string
    {
        return $this->additionalInfo;
    }

    public function getSource(): string
    {
        return $this->source;
    }
}
