<?php

namespace App\Booking\Application\Event;

final class BookingStatusChanged
{
    public function __construct(
        private readonly string $bookingId,
        private readonly string $providerId,
        private readonly ?string $userId,
        private readonly string $fromStatus,
        private readonly string $toStatus,
        private readonly ?string $changedBy = null,
        private readonly ?string $cancelReason = null
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

    public function getFromStatus(): string
    {
        return $this->fromStatus;
    }

    public function getToStatus(): string
    {
        return $this->toStatus;
    }

    public function getChangedBy(): ?string
    {
        return $this->changedBy;
    }

    public function getCancelReason(): ?string
    {
        return $this->cancelReason;
    }
}
