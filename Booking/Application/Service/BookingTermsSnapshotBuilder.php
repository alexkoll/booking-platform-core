<?php

namespace App\Booking\Application\Service;

use App\Provider\Domain\Entity\Provider;
use App\Provider\Domain\ValueObject\EmployeeId;
use App\Provider\Domain\ValueObject\ProviderLocationId;

final class BookingTermsSnapshotBuilder
{
    public const VERSION = '2026-03-27';

    public function build(
        Provider $provider,
        ProviderLocationId $addressId,
        ?EmployeeId $employeeId,
        array $snapshot,
        BookingPaymentPolicy $paymentPolicy,
        ?\DateTimeImmutable $paymentDeadlineAt
    ): array {
        return [
            'type' => 'booking_terms',
            'version' => self::VERSION,
            'providerId' => (string) $provider->getId(),
            'providerName' => $provider->getName(),
            'addressId' => (string) $addressId,
            'employeeId' => $employeeId ? (string) $employeeId : null,
            'services' => array_map(
                static fn(array $item): array => [
                    'id' => isset($item['id']) ? (string) $item['id'] : null,
                    'employeeServiceId' => isset($item['employeeServiceId']) ? (string) $item['employeeServiceId'] : null,
                    'name' => isset($item['name']) ? (string) $item['name'] : null,
                    'price' => isset($item['price']) && is_numeric($item['price']) ? (float) $item['price'] : null,
                    'currency' => isset($item['currency']) ? (string) $item['currency'] : null,
                    'durationMinutes' => isset($item['durationMinutes']) ? (int) $item['durationMinutes'] : null,
                    'promoId' => isset($item['promoId']) ? (string) $item['promoId'] : null,
                ],
                $snapshot
            ),
            'payment' => [
                'required' => $paymentPolicy->required,
                'type' => $paymentPolicy->paymentType,
                'depositMode' => $paymentPolicy->depositMode,
                'depositValue' => $paymentPolicy->depositValue,
                'amountDue' => $paymentPolicy->amountDue,
                'currency' => $paymentPolicy->currency,
                'deadlineAt' => $paymentDeadlineAt?->format(DATE_ATOM),
            ],
            'cancellation' => [
                'rule' => 'depends on provider policy and booking status',
            ],
            'refund' => [
                'rule' => 'depends on payment status, cancellation timing, and provider policy',
            ],
        ];
    }
}
