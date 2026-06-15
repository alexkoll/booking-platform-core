<?php

namespace App\Booking\Infrastructure\Doctrine\Entity;

use App\Booking\Domain\Entity\Booking;
use App\Booking\Domain\ValueObject\BookingId;
use App\Booking\Domain\ValueObject\BookingSource;
use App\Booking\Domain\ValueObject\BookingStatus;
use App\Identity\Domain\ValueObject\UserId;
use App\Provider\Domain\ValueObject\EmployeeId;
use App\Provider\Domain\ValueObject\ProviderId;
use App\Provider\Domain\ValueObject\ProviderLocationId;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'bookings')]
class BookingDoctrine
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(type: 'string', length: 36, name: 'provider_id')]
    private string $providerId;

    #[ORM\Column(type: 'string', length: 36, name: 'user_id', nullable: true)]
    private ?string $userId = null;

    #[ORM\Column(type: 'string', length: 36, name: 'address_id')]
    private string $addressId;

    #[ORM\Column(type: 'string', length: 36, name: 'employee_id', nullable: true)]
    private ?string $employeeId = null;

    #[ORM\Column(type: 'string', length: 255, name: 'client_name', nullable: true)]
    private ?string $clientName = null;

    #[ORM\Column(type: 'text', name: 'additional_info', nullable: true)]
    private ?string $additionalInfo = null;

    #[ORM\Column(type: 'string', length: 16, name: 'source', options: ['default' => BookingSource::ONLINE])]
    private string $source = BookingSource::ONLINE;

    #[ORM\Column(type: 'string', length: 16)]
    private string $status = BookingStatus::NEW;

    #[ORM\Column(type: 'date_immutable', name: 'booking_date')]
    private \DateTimeImmutable $bookingDate;

    #[ORM\Column(type: 'time', name: 'booking_time')]
    private \DateTimeInterface $bookingTime;

    #[ORM\Column(type: 'integer', name: 'duration_minutes')]
    private int $durationMinutes;

    #[ORM\Column(type: 'json', name: 'services_snapshot')]
    private array $servicesSnapshot = [];

    #[ORM\Column(type: 'boolean', name: 'payment_required', options: ['default' => false])]
    private bool $paymentRequired = false;

    #[ORM\Column(type: 'string', length: 16, name: 'payment_type', options: ['default' => 'none'])]
    private string $paymentType = 'none';

    #[ORM\Column(type: 'string', length: 16, name: 'deposit_mode', nullable: true)]
    private ?string $depositMode = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, name: 'deposit_value', nullable: true)]
    private ?string $depositValue = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, name: 'payment_amount_due', nullable: true)]
    private ?string $paymentAmountDue = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, name: 'custom_total_price', nullable: true)]
    private ?string $customTotalPrice = null;

    #[ORM\Column(type: 'string', length: 3, nullable: true)]
    private ?string $currency = null;

    #[ORM\Column(type: 'string', length: 16, name: 'payment_status', options: ['default' => 'none'])]
    private string $paymentStatus = 'none';

    #[ORM\Column(type: 'datetime_immutable', name: 'payment_deadline_at', nullable: true)]
    private ?\DateTimeImmutable $paymentDeadlineAt = null;

    #[ORM\Column(type: 'datetime_immutable', name: 'paid_at', nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    #[ORM\Column(type: 'string', length: 255, name: 'payment_intent_id', nullable: true)]
    private ?string $paymentIntentId = null;

    #[ORM\Column(type: 'string', length: 16, name: 'payment_provider', nullable: true)]
    private ?string $paymentProvider = null;

    #[ORM\Column(type: 'string', length: 32, name: 'terms_version', nullable: true)]
    private ?string $termsVersion = null;

    #[ORM\Column(type: 'datetime_immutable', name: 'terms_accepted_at', nullable: true)]
    private ?\DateTimeImmutable $termsAcceptedAt = null;

    #[ORM\Column(type: 'json', name: 'terms_snapshot', nullable: true)]
    private ?array $termsSnapshot = null;

    #[ORM\Column(type: 'string', length: 16, name: 'cancelled_by', nullable: true)]
    private ?string $cancelledBy = null;

    #[ORM\Column(type: 'datetime_immutable', name: 'cancelled_at', nullable: true)]
    private ?\DateTimeImmutable $cancelledAt = null;

    #[ORM\Column(type: 'string', length: 64, name: 'cancel_reason', nullable: true)]
    private ?string $cancelReason = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public static function fromDomain(Booking $booking): self
    {
        $self = new self();
        $self->id = (string) $booking->getId();
        $self->providerId = (string) $booking->getProviderId();
        $self->userId = $booking->getUserId() ? (string) $booking->getUserId() : null;
        $self->addressId = (string) $booking->getAddressId();
        $self->employeeId = $booking->getEmployeeId() ? (string) $booking->getEmployeeId() : null;
        $self->status = $booking->getStatus();
        $self->bookingDate = new \DateTimeImmutable($booking->getBookingDate());
        $self->bookingTime = new \DateTimeImmutable($booking->getBookingTime());
        $self->durationMinutes = $booking->getDurationMinutes();
        $self->servicesSnapshot = $booking->getServicesSnapshot();
        $self->paymentRequired = $booking->isPaymentRequired();
        $self->paymentType = $booking->getPaymentType();
        $self->depositMode = $booking->getDepositMode();
        $self->depositValue = $booking->getDepositValue() !== null ? (string) $booking->getDepositValue() : null;
        $self->paymentAmountDue = $booking->getPaymentAmountDue() !== null ? (string) $booking->getPaymentAmountDue() : null;
        $self->customTotalPrice = $booking->getCustomTotalPrice() !== null ? (string) $booking->getCustomTotalPrice() : null;
        $self->currency = $booking->getCurrency();
        $self->paymentStatus = $booking->getPaymentStatus();
        $self->paymentDeadlineAt = $booking->getPaymentDeadlineAt();
        $self->paidAt = $booking->getPaidAt();
        $self->paymentIntentId = $booking->getPaymentIntentId();
        $self->paymentProvider = $booking->getPaymentProvider();
        $self->termsVersion = $booking->getTermsVersion();
        $self->termsAcceptedAt = $booking->getTermsAcceptedAt();
        $self->termsSnapshot = $booking->getTermsSnapshot();
        $self->cancelledBy = $booking->getCancelledBy();
        $self->cancelledAt = $booking->getCancelledAt();
        $self->cancelReason = $booking->getCancelReason();
        $self->clientName = $booking->getClientName();
        $self->additionalInfo = $booking->getAdditionalInfo();
        $self->source = $booking->getSource();
        $self->createdAt = $booking->getCreatedAt();
        $self->updatedAt = $booking->getUpdatedAt();

        return $self;
    }

    public function updateFromDomain(Booking $booking): void
    {
        $this->providerId = (string) $booking->getProviderId();
        $this->userId = $booking->getUserId() ? (string) $booking->getUserId() : null;
        $this->addressId = (string) $booking->getAddressId();
        $this->employeeId = $booking->getEmployeeId() ? (string) $booking->getEmployeeId() : null;
        $this->status = $booking->getStatus();
        $this->bookingDate = new \DateTimeImmutable($booking->getBookingDate());
        $this->bookingTime = new \DateTimeImmutable($booking->getBookingTime());
        $this->durationMinutes = $booking->getDurationMinutes();
        $this->servicesSnapshot = $booking->getServicesSnapshot();
        $this->paymentRequired = $booking->isPaymentRequired();
        $this->paymentType = $booking->getPaymentType();
        $this->depositMode = $booking->getDepositMode();
        $this->depositValue = $booking->getDepositValue() !== null ? (string) $booking->getDepositValue() : null;
        $this->paymentAmountDue = $booking->getPaymentAmountDue() !== null ? (string) $booking->getPaymentAmountDue() : null;
        $this->customTotalPrice = $booking->getCustomTotalPrice() !== null ? (string) $booking->getCustomTotalPrice() : null;
        $this->currency = $booking->getCurrency();
        $this->paymentStatus = $booking->getPaymentStatus();
        $this->paymentDeadlineAt = $booking->getPaymentDeadlineAt();
        $this->paidAt = $booking->getPaidAt();
        $this->paymentIntentId = $booking->getPaymentIntentId();
        $this->paymentProvider = $booking->getPaymentProvider();
        $this->termsVersion = $booking->getTermsVersion();
        $this->termsAcceptedAt = $booking->getTermsAcceptedAt();
        $this->termsSnapshot = $booking->getTermsSnapshot();
        $this->cancelledBy = $booking->getCancelledBy();
        $this->cancelledAt = $booking->getCancelledAt();
        $this->cancelReason = $booking->getCancelReason();
        $this->clientName = $booking->getClientName();
        $this->additionalInfo = $booking->getAdditionalInfo();
        $this->source = $booking->getSource();
        $this->createdAt = $booking->getCreatedAt();
        $this->updatedAt = $booking->getUpdatedAt();
    }

    public function toDomain(): Booking
    {
        $status = $this->status === 'new' ? BookingStatus::PENDING : $this->status;

        return Booking::reconstitute(
            BookingId::fromString($this->id),
            ProviderId::fromString($this->providerId),
            $this->userId ? UserId::fromString($this->userId) : null,
            ProviderLocationId::fromString($this->addressId),
            $this->employeeId ? EmployeeId::fromString($this->employeeId) : null,
            $status,
            $this->bookingDate->format('Y-m-d'),
            $this->bookingTime->format('H:i:s'),
            $this->durationMinutes,
            $this->servicesSnapshot,
            $this->paymentRequired,
            $this->paymentType,
            $this->depositMode,
            $this->depositValue !== null ? (float) $this->depositValue : null,
            $this->paymentAmountDue !== null ? (float) $this->paymentAmountDue : null,
            $this->customTotalPrice !== null ? (float) $this->customTotalPrice : null,
            $this->currency,
            $this->paymentStatus,
            $this->paymentDeadlineAt,
            $this->paidAt,
            $this->paymentIntentId,
            $this->paymentProvider,
            $this->termsVersion,
            $this->termsAcceptedAt,
            $this->termsSnapshot,
            $this->clientName ?? '',
            $this->additionalInfo,
            $this->source ?? BookingSource::ONLINE,
            $this->createdAt,
            $this->updatedAt,
            $this->cancelledBy,
            $this->cancelledAt,
            $this->cancelReason
        );
    }

    public function getId(): string
    {
        return $this->id;
    }
}
