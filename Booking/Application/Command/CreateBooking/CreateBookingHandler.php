<?php

namespace App\Booking\Application\Command\CreateBooking;

use App\Booking\Application\Event\BookingCreated;
use App\Booking\Application\Service\BookingAvailabilityChecker;
use App\Booking\Application\Service\BookingCreationContextResolver;
use App\Booking\Application\Service\BookingPaymentPolicyResolver;
use App\Booking\Application\Service\BookingPromoApplicator;
use App\Booking\Application\Service\BookingServiceSnapshotBuilder;
use App\Booking\Application\Service\BookingTermsSnapshotBuilder;
use App\Booking\Domain\Entity\Booking;
use App\Booking\Domain\Exception\BookingPaymentPolicyException;
use App\Booking\Domain\Exception\BookingSlotUnavailableException;
use App\Booking\Domain\Exception\BookingTermsNotAcceptedException;
use App\Booking\Domain\Repository\BookingRepository;
use App\Booking\Domain\ValueObject\BookingSource;
use App\Booking\Domain\ValueObject\BookingStatus;
use App\Identity\Domain\ValueObject\UserId;
use App\Payment\Domain\Entity\ProviderPaymentSettings;
use App\Provider\Domain\Repository\ProviderPromoRepository;
use App\Provider\Domain\ValueObject\EmployeeId;
use App\Provider\Domain\ValueObject\ProviderId;
use App\Provider\Domain\ValueObject\ProviderLocationId;
use RuntimeException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class CreateBookingHandler
{
    private const PAYMENT_DEADLINE_MINUTES = 30;

    public function __construct(
        private readonly BookingRepository $bookings,
        private readonly BookingCreationContextResolver $contextResolver,
        private readonly BookingServiceSnapshotBuilder $snapshotBuilder,
        private readonly BookingPromoApplicator $promoApplicator,
        private readonly BookingAvailabilityChecker $availability,
        private readonly BookingPaymentPolicyResolver $paymentPolicies,
        private readonly BookingTermsSnapshotBuilder $termsSnapshots,
        private readonly ProviderPromoRepository $promos,
        private readonly MessageBusInterface $bus,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function __invoke(CreateBookingCommand $command): Booking
    {
        [$providerId, $addressId] = $this->parseProviderAndAddress($command);
        [$date, $time] = $this->parseDateAndTime($command);
        $employeeId = $this->parseEmployeeId($command->employeeId);

        $context = $this->contextResolver->resolveOnline($providerId, $addressId, $employeeId);
        $this->availability->assertOnlineNotPast($context->provider, $date, $time);

        $snapshot = $this->snapshotBuilder->buildOnline($context->provider, $addressId, $command->employeeServiceIds, $employeeId);
        if ($command->promoIds) {
            $snapshot = $this->promoApplicator->applySelectedPromos(
                $snapshot,
                $this->promos->findByProviderAndDate($providerId, $date),
                $command->promoIds
            );
        }

        $durationMinutes = $this->snapshotBuilder->calculateDurationMinutes($snapshot);
        $this->availability->assertOnlineAvailable($providerId, $addressId, $date, $time, $durationMinutes, $employeeId);

        $paymentPolicy = $this->paymentPolicies->resolve($providerId, $snapshot);
        $autoConfirm = $context->provider->isAutoConfirm();
        $paymentStatus = $paymentPolicy->required ? Booking::PAYMENT_STATUS_PENDING : Booking::PAYMENT_STATUS_NONE;
        $paymentDeadlineAt = null;
        $status = BookingStatus::PENDING;

        if (!$paymentPolicy->required && $autoConfirm) {
            $status = BookingStatus::CONFIRMED;
        }

        if ($paymentPolicy->required && $paymentPolicy->chargeMode === ProviderPaymentSettings::CHARGE_ON_CONFIRM && $autoConfirm) {
            $paymentDeadlineAt = new \DateTimeImmutable('+' . self::PAYMENT_DEADLINE_MINUTES . ' minutes');
            $status = BookingStatus::CONFIRMED;
        }

        if ($paymentPolicy->required && (!$paymentPolicy->currency || $paymentPolicy->amountDue === null || $paymentPolicy->amountDue <= 0)) {
            throw new BookingPaymentPolicyException('Payment settings are invalid');
        }

        if (!$command->termsAccepted) {
            throw new BookingTermsNotAcceptedException();
        }

        $termsAcceptedAt = new \DateTimeImmutable();
        $termsSnapshot = $this->termsSnapshots->build($context->provider, $addressId, $employeeId, $snapshot, $paymentPolicy, $paymentDeadlineAt);

        $booking = Booking::create(
            $providerId,
            UserId::fromString($command->userId),
            $addressId,
            $snapshot,
            $date,
            $time,
            $durationMinutes,
            $status,
            $employeeId,
            $paymentPolicy->required,
            $paymentPolicy->paymentType,
            $paymentPolicy->depositMode,
            $paymentPolicy->depositValue,
            $paymentPolicy->amountDue,
            null,
            $paymentPolicy->currency,
            $paymentStatus,
            $paymentDeadlineAt,
            null,
            null,
            null,
            BookingTermsSnapshotBuilder::VERSION,
            $termsAcceptedAt,
            $termsSnapshot,
            $command->clientName ?: '',
            $command->additionalInfo,
            BookingSource::ONLINE
        );

        try {
            $this->bookings->add($booking);
        } catch (BookingSlotUnavailableException $exception) {
            throw new RuntimeException($this->translator->trans('booking.slot_unavailable'), 0, $exception);
        }

        $this->bus->dispatch(new BookingCreated(
            (string) $booking->getId(),
            (string) $booking->getProviderId(),
            $booking->getUserId() ? (string) $booking->getUserId() : null,
            (string) $booking->getAddressId(),
            $booking->getBookingDate(),
            $booking->getBookingTime(),
            $booking->getDurationMinutes(),
            $booking->getStatus(),
            $booking->getClientName(),
            $booking->getAdditionalInfo(),
            $booking->getSource()
        ));

        return $booking;
    }

    /**
     * @return array{ProviderId, ProviderLocationId}
     */
    private function parseProviderAndAddress(CreateBookingCommand $command): array
    {
        try {
            return [
                ProviderId::fromString($command->providerId),
                ProviderLocationId::fromString($command->addressId),
            ];
        } catch (\Throwable) {
            throw new RuntimeException('Invalid provider or address id');
        }
    }

    /**
     * @return array{string, string}
     */
    private function parseDateAndTime(CreateBookingCommand $command): array
    {
        $dateObj = \DateTimeImmutable::createFromFormat('Y-m-d', $command->date);
        if (!$dateObj) {
            throw new RuntimeException('Invalid date format');
        }

        $timeObj = \DateTimeImmutable::createFromFormat('H:i', $command->time)
            ?: \DateTimeImmutable::createFromFormat('H:i:s', $command->time);
        if (!$timeObj) {
            throw new RuntimeException('Invalid time format');
        }

        return [$dateObj->format('Y-m-d'), $timeObj->format('H:i:s')];
    }

    private function parseEmployeeId(?string $value): ?EmployeeId
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return EmployeeId::fromString($value);
        } catch (\Throwable) {
            throw new RuntimeException('Invalid employee id');
        }
    }
}
