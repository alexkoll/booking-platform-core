<?php

namespace App\Booking\Domain\Entity;

use App\Booking\Domain\ValueObject\BookingId;
use App\Booking\Domain\ValueObject\BookingSlot;
use App\Booking\Domain\ValueObject\BookingSource;
use App\Booking\Domain\ValueObject\BookingStatus;
use App\Identity\Domain\ValueObject\UserId;
use App\Provider\Domain\ValueObject\ProviderId;
use App\Provider\Domain\ValueObject\EmployeeId;
use App\Provider\Domain\ValueObject\ProviderLocationId;
use DateTimeImmutable;
use DateTimeZone;

final class Booking
{
    public const PAYMENT_STATUS_NONE = 'none';
    public const PAYMENT_STATUS_PENDING = 'pending';
    public const PAYMENT_STATUS_PAID = 'paid';
    public const PAYMENT_STATUS_FAILED = 'failed';
    public const PAYMENT_STATUS_REFUNDED = 'refunded';
    public const CANCELLED_BY_CLIENT = 'client';
    public const CANCELLED_BY_PROVIDER = 'provider';
    public const CANCELLED_BY_SYSTEM = 'system';
    public const CANCEL_REASON_CLIENT_BEFORE_DEADLINE = 'client_cancelled_before_deadline';
    public const CANCEL_REASON_PROVIDER_BEFORE_START = 'provider_cancelled_before_start';

    private BookingId $id;
    private ProviderId $providerId;
    private ?UserId $userId;
    private ProviderLocationId $addressId;
    private ?EmployeeId $employeeId;
    private string $clientName;
    private ?string $additionalInfo;
    private string $source;
    private string $status;
    private string $bookingDate;
    private string $bookingTime;
    private int $durationMinutes;
    private array $servicesSnapshot;
    private bool $paymentRequired;
    private string $paymentType;
    private ?string $depositMode;
    private ?float $depositValue;
    private ?float $paymentAmountDue;
    private ?float $customTotalPrice;
    private ?string $currency;
    private string $paymentStatus;
    private ?DateTimeImmutable $paymentDeadlineAt;
    private ?DateTimeImmutable $paidAt;
    private ?string $paymentIntentId;
    private ?string $paymentProvider;
    private ?string $termsVersion;
    private ?DateTimeImmutable $termsAcceptedAt;
    private ?array $termsSnapshot;
    private ?string $cancelledBy;
    private ?DateTimeImmutable $cancelledAt;
    private ?string $cancelReason;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    private function __construct(
        BookingId $id,
        ProviderId $providerId,
        ?UserId $userId,
        ProviderLocationId $addressId,
        ?EmployeeId $employeeId,
        string $status,
        string $bookingDate,
        string $bookingTime,
        int $durationMinutes,
        array $servicesSnapshot,
        bool $paymentRequired,
        string $paymentType,
        ?string $depositMode,
        ?float $depositValue,
        ?float $paymentAmountDue,
        ?float $customTotalPrice,
        ?string $currency,
        string $paymentStatus,
        ?DateTimeImmutable $paymentDeadlineAt,
        ?DateTimeImmutable $paidAt,
        ?string $paymentIntentId,
        ?string $paymentProvider,
        ?string $termsVersion,
        ?DateTimeImmutable $termsAcceptedAt,
        ?array $termsSnapshot,
        string $clientName,
        ?string $additionalInfo,
        string $source,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
        ?string $cancelledBy,
        ?DateTimeImmutable $cancelledAt,
        ?string $cancelReason
    ) {
        BookingStatus::assertValid($status);
        BookingSource::assertValid($source);
        $slot = BookingSlot::fromDateTimeParts($bookingDate, $bookingTime, $durationMinutes);
        if ($cancelledBy !== null && !in_array($cancelledBy, [self::CANCELLED_BY_CLIENT, self::CANCELLED_BY_PROVIDER, self::CANCELLED_BY_SYSTEM], true)) {
            throw new \InvalidArgumentException('Invalid cancellation initiator');
        }
        $this->id = $id;
        $this->providerId = $providerId;
        $this->userId = $userId;
        $this->addressId = $addressId;
        $this->employeeId = $employeeId;
        $this->status = $status;
        $this->bookingDate = $slot->date();
        $this->bookingTime = $slot->time();
        $this->durationMinutes = $slot->durationMinutes();
        $this->servicesSnapshot = $servicesSnapshot;
        $this->paymentRequired = $paymentRequired;
        $this->paymentType = $paymentType;
        $this->depositMode = $depositMode;
        $this->depositValue = $depositValue;
        $this->paymentAmountDue = $paymentAmountDue;
        $this->customTotalPrice = $customTotalPrice;
        $this->currency = $currency;
        $this->paymentStatus = $paymentStatus;
        $this->paymentDeadlineAt = $paymentDeadlineAt;
        $this->paidAt = $paidAt;
        $this->paymentIntentId = $paymentIntentId;
        $this->paymentProvider = $paymentProvider;
        $this->termsVersion = $termsVersion;
        $this->termsAcceptedAt = $termsAcceptedAt;
        $this->termsSnapshot = $termsSnapshot;
        $this->clientName = $clientName;
        $this->additionalInfo = $additionalInfo;
        $this->source = $source;
        $this->cancelledBy = $cancelledBy;
        $this->cancelledAt = $cancelledAt;
        $this->cancelReason = $cancelReason;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public static function create(
        ProviderId $providerId,
        ?UserId $userId,
        ProviderLocationId $addressId,
        array $servicesSnapshot,
        string $bookingDate,
        string $bookingTime,
        int $durationMinutes,
        string $status = BookingStatus::NEW,
        ?EmployeeId $employeeId = null,
        bool $paymentRequired = false,
        string $paymentType = 'none',
        ?string $depositMode = null,
        ?float $depositValue = null,
        ?float $paymentAmountDue = null,
        ?float $customTotalPrice = null,
        ?string $currency = null,
        string $paymentStatus = 'none',
        ?DateTimeImmutable $paymentDeadlineAt = null,
        ?DateTimeImmutable $paidAt = null,
        ?string $paymentIntentId = null,
        ?string $paymentProvider = null,
        ?string $termsVersion = null,
        ?DateTimeImmutable $termsAcceptedAt = null,
        ?array $termsSnapshot = null,
        string $clientName = '',
        ?string $additionalInfo = null,
        string $source = BookingSource::ONLINE,
        ?string $cancelledBy = null,
        ?DateTimeImmutable $cancelledAt = null,
        ?string $cancelReason = null
    ): self {
        $now = new DateTimeImmutable();
        return new self(
            BookingId::generate(),
            $providerId,
            $userId,
            $addressId,
            $employeeId,
            $status,
            $bookingDate,
            $bookingTime,
            $durationMinutes,
            $servicesSnapshot,
            $paymentRequired,
            $paymentType,
            $depositMode,
            $depositValue,
            $paymentAmountDue,
            $customTotalPrice,
            $currency,
            $paymentStatus,
            $paymentDeadlineAt,
            $paidAt,
            $paymentIntentId,
            $paymentProvider,
            $termsVersion,
            $termsAcceptedAt,
            $termsSnapshot,
            $clientName,
            $additionalInfo,
            $source,
            $now,
            $now,
            $cancelledBy,
            $cancelledAt,
            $cancelReason
        );
    }

    public static function reconstitute(
        BookingId $id,
        ProviderId $providerId,
        ?UserId $userId,
        ProviderLocationId $addressId,
        ?EmployeeId $employeeId,
        string $status,
        string $bookingDate,
        string $bookingTime,
        int $durationMinutes,
        array $servicesSnapshot,
        bool $paymentRequired,
        string $paymentType,
        ?string $depositMode,
        ?float $depositValue,
        ?float $paymentAmountDue,
        ?float $customTotalPrice,
        ?string $currency,
        string $paymentStatus,
        ?DateTimeImmutable $paymentDeadlineAt,
        ?DateTimeImmutable $paidAt,
        ?string $paymentIntentId,
        ?string $paymentProvider,
        ?string $termsVersion,
        ?DateTimeImmutable $termsAcceptedAt,
        ?array $termsSnapshot,
        string $clientName,
        ?string $additionalInfo,
        string $source,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
        ?string $cancelledBy = null,
        ?DateTimeImmutable $cancelledAt = null,
        ?string $cancelReason = null
    ): self {
        return new self(
            $id,
            $providerId,
            $userId,
            $addressId,
            $employeeId,
            $status,
            $bookingDate,
            $bookingTime,
            $durationMinutes,
            $servicesSnapshot,
            $paymentRequired,
            $paymentType,
            $depositMode,
            $depositValue,
            $paymentAmountDue,
            $customTotalPrice,
            $currency,
            $paymentStatus,
            $paymentDeadlineAt,
            $paidAt,
            $paymentIntentId,
            $paymentProvider,
            $termsVersion,
            $termsAcceptedAt,
            $termsSnapshot,
            $clientName,
            $additionalInfo,
            $source,
            $createdAt,
            $updatedAt,
            $cancelledBy,
            $cancelledAt,
            $cancelReason
        );
    }

    public function getId(): BookingId
    {
        return $this->id;
    }

    public function getProviderId(): ProviderId
    {
        return $this->providerId;
    }

    public function getUserId(): ?UserId
    {
        return $this->userId;
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

    public function getAddressId(): ProviderLocationId
    {
        return $this->addressId;
    }

    public function getEmployeeId(): ?EmployeeId
    {
        return $this->employeeId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getBookingDate(): string
    {
        return $this->bookingDate;
    }

    public function getBookingTime(): string
    {
        return $this->bookingTime;
    }

    public function getStartsAt(): DateTimeImmutable
    {
        return $this->getSlot()->startsAt();
    }

    public function getStartsAtIn(DateTimeZone $timezone): DateTimeImmutable
    {
        return $this->getSlot()->startsAtIn($timezone);
    }

    public function getEndsAt(): DateTimeImmutable
    {
        return $this->getSlot()->endsAt();
    }

    public function getEndsAtIn(DateTimeZone $timezone): DateTimeImmutable
    {
        return $this->getSlot()->endsAtIn($timezone);
    }

    public function getSlot(): BookingSlot
    {
        return BookingSlot::fromDateTimeParts($this->bookingDate, $this->bookingTime, $this->durationMinutes);
    }

    public function getDurationMinutes(): int
    {
        return $this->durationMinutes;
    }

    public function getServicesSnapshot(): array
    {
        return $this->servicesSnapshot;
    }

    public function isPaymentRequired(): bool
    {
        return $this->paymentRequired;
    }

    public function getPaymentType(): string
    {
        return $this->paymentType;
    }

    public function getDepositMode(): ?string
    {
        return $this->depositMode;
    }

    public function getDepositValue(): ?float
    {
        return $this->depositValue;
    }

    public function getPaymentAmountDue(): ?float
    {
        return $this->paymentAmountDue;
    }

    public function getCustomTotalPrice(): ?float
    {
        return $this->customTotalPrice;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function getPaymentStatus(): string
    {
        return $this->paymentStatus;
    }

    public function getPaymentDeadlineAt(): ?DateTimeImmutable
    {
        return $this->paymentDeadlineAt;
    }

    public function getPaidAt(): ?DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function getPaymentIntentId(): ?string
    {
        return $this->paymentIntentId;
    }

    public function getPaymentProvider(): ?string
    {
        return $this->paymentProvider;
    }

    public function getTermsVersion(): ?string
    {
        return $this->termsVersion;
    }

    public function getTermsAcceptedAt(): ?DateTimeImmutable
    {
        return $this->termsAcceptedAt;
    }

    public function getTermsSnapshot(): ?array
    {
        return $this->termsSnapshot;
    }

    public function getCancelledBy(): ?string
    {
        return $this->cancelledBy;
    }

    public function getCancelledAt(): ?DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function getCancelReason(): ?string
    {
        return $this->cancelReason;
    }

    public function setPaymentIntent(?string $paymentIntentId, ?string $paymentProvider): void
    {
        $this->paymentIntentId = $paymentIntentId;
        $this->paymentProvider = $paymentProvider;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function markPaymentPending(?DateTimeImmutable $deadlineAt): void
    {
        $this->paymentStatus = self::PAYMENT_STATUS_PENDING;
        $this->paymentDeadlineAt = $deadlineAt;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function markPaymentPaid(): void
    {
        $this->paymentStatus = self::PAYMENT_STATUS_PAID;
        $this->paidAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function markPaymentFailed(): void
    {
        $this->paymentStatus = self::PAYMENT_STATUS_FAILED;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function markPaymentRefunded(): void
    {
        $this->paymentStatus = self::PAYMENT_STATUS_REFUNDED;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function changeStatus(string $status): void
    {
        BookingStatus::assertValid($status);
        $this->status = $status;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function confirm(): void
    {
        $this->changeStatus(BookingStatus::CONFIRMED);
    }

    public function complete(): void
    {
        if ($this->status !== BookingStatus::CONFIRMED) {
            throw new \DomainException('Only confirmed bookings can be completed');
        }

        $this->changeStatus(BookingStatus::COMPLETED);
    }

    public function markNoShow(): void
    {
        if ($this->status !== BookingStatus::CONFIRMED) {
            throw new \DomainException('Only confirmed bookings can be marked as no show');
        }

        $this->changeStatus(BookingStatus::NO_SHOW);
    }

    public function expire(): void
    {
        $this->changeStatus(BookingStatus::EXPIRED);
    }

    public function cancel(string $by, string $reason, DateTimeImmutable $now): void
    {
        if (!in_array($by, [self::CANCELLED_BY_CLIENT, self::CANCELLED_BY_PROVIDER, self::CANCELLED_BY_SYSTEM], true)) {
            throw new \InvalidArgumentException('Invalid cancellation initiator');
        }

        $this->status = BookingStatus::CANCELLED;
        $this->cancelledBy = $by;
        $this->cancelledAt = $now;
        $this->cancelReason = $reason;
        $this->updatedAt = $now;
    }
}
