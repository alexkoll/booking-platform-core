<?php

namespace App\Booking\Application\Service;

use App\Payment\Domain\Entity\ProviderPaymentSettings;
use App\Payment\Domain\Repository\EmployeeServicePaymentOverrideRepository;
use App\Payment\Domain\Repository\ProviderPaymentAccountRepository;
use App\Payment\Domain\Repository\ProviderPaymentSettingsRepository;
use App\Payment\Domain\Repository\ServicePaymentOverrideRepository;
use App\Provider\Domain\ValueObject\ProviderId;
use App\Provider\Domain\ValueObject\ServiceId;

final class BookingPaymentPolicyResolver
{
    public function __construct(
        private readonly ProviderPaymentSettingsRepository $paymentSettings,
        private readonly ProviderPaymentAccountRepository $paymentAccounts,
        private readonly ServicePaymentOverrideRepository $servicePaymentOverrides,
        private readonly EmployeeServicePaymentOverrideRepository $employeeServicePaymentOverrides,
        private readonly bool $employeePaymentOverridesEnabled,
        private readonly bool $servicePaymentOverridesEnabled,
    ) {
    }

    public function resolve(ProviderId $providerId, array $snapshot): BookingPaymentPolicy
    {
        $currency = null;
        foreach ($snapshot as $item) {
            if (!empty($item['currency'])) {
                if ($currency === null) {
                    $currency = (string) $item['currency'];
                } elseif ($currency !== (string) $item['currency']) {
                    $currency = null;
                    break;
                }
            }
        }

        $override = null;
        if ($this->employeePaymentOverridesEnabled) {
            foreach ($snapshot as $item) {
                if (empty($item['employeeServiceId'])) {
                    continue;
                }
                try {
                    $employeeOverride = $this->employeeServicePaymentOverrides->byEmployeeServiceId(
                        ServiceId::fromString((string) $item['employeeServiceId'])
                    );
                    if ($employeeOverride) {
                        $override = $employeeOverride;
                        break;
                    }
                } catch (\Throwable) {
                    continue;
                }
            }
        }

        if (!$override && $this->servicePaymentOverridesEnabled) {
            foreach ($snapshot as $item) {
                if (empty($item['id'])) {
                    continue;
                }
                try {
                    $serviceOverride = $this->servicePaymentOverrides->byServiceId(ServiceId::fromString((string) $item['id']));
                    if ($serviceOverride) {
                        $override = $serviceOverride;
                        break;
                    }
                } catch (\Throwable) {
                    continue;
                }
            }
        }

        $settings = $this->paymentSettings->byProviderId($providerId);
        if (!$settings) {
            $settings = ProviderPaymentSettings::create($providerId, ProviderPaymentSettings::TYPE_NONE);
        }

        $hasActiveGateway = false;
        foreach ($this->paymentAccounts->listByProviderId($providerId) as $account) {
            if (!$account->isChargesEnabled()) {
                continue;
            }
            if (!$account->getAccountId()) {
                continue;
            }
            $hasActiveGateway = true;
            break;
        }

        $paymentType = $override ? $override->getPaymentType() : $settings->getDefaultPaymentType();
        $depositMode = $override ? $override->getDepositMode() : $settings->getDefaultDepositMode();
        $depositValue = $override ? $override->getDepositValue() : $settings->getDefaultDepositValue();
        $currency = $settings->getCurrency() ?? $currency;

        if (!$hasActiveGateway) {
            $paymentType = ProviderPaymentSettings::TYPE_NONE;
            $depositMode = null;
            $depositValue = null;
        }

        $totalPrice = $this->calculateTotalPrice($snapshot);
        $amountDue = null;
        $required = false;

        if ($paymentType === ProviderPaymentSettings::TYPE_DEPOSIT) {
            $required = true;
            if ($depositMode === ProviderPaymentSettings::DEPOSIT_PERCENT && $depositValue !== null) {
                $amountDue = $totalPrice * ($depositValue / 100);
            } elseif ($depositMode === ProviderPaymentSettings::DEPOSIT_FIXED && $depositValue !== null) {
                $amountDue = $depositValue;
            }
        } elseif ($paymentType === ProviderPaymentSettings::TYPE_FULL) {
            $required = true;
            $amountDue = $totalPrice;
        }

        if ($amountDue !== null && $amountDue < 0) {
            $amountDue = null;
        }

        return new BookingPaymentPolicy(
            $required,
            $paymentType,
            $depositMode,
            $depositValue,
            $amountDue,
            $currency,
            ProviderPaymentSettings::CHARGE_ON_CONFIRM,
        );
    }

    private function calculateTotalPrice(array $snapshot): float
    {
        $total = 0.0;
        foreach ($snapshot as $item) {
            $price = $item['price'] ?? null;
            if (is_numeric($price)) {
                $total += (float) $price;
            }
        }

        return $total;
    }
}
