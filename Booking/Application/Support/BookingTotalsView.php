<?php

namespace App\Booking\Application\Support;

final class BookingTotalsView
{
    /**
     * @param array<int, mixed> $services
     * @return array{servicesTotalPrice: float, customTotalPrice: ?float, totalPrice: float, currency: ?string}
     */
    public static function build(array $services, ?float $customTotalPrice): array
    {
        $servicesTotalPrice = 0.0;
        $currency = null;

        foreach ($services as $service) {
            if (!is_array($service)) {
                continue;
            }

            if ($currency === null) {
                $serviceCurrency = isset($service['currency']) ? (string) $service['currency'] : '';
                if ($serviceCurrency !== '') {
                    $currency = $serviceCurrency;
                }
            }

            if (is_numeric($service['price'] ?? null)) {
                $servicesTotalPrice += (float) $service['price'];
            }
        }

        $normalizedCustomTotalPrice = null;
        if ($customTotalPrice !== null && abs($customTotalPrice - $servicesTotalPrice) >= 0.01) {
            $normalizedCustomTotalPrice = $customTotalPrice;
        }

        return [
            'servicesTotalPrice' => $servicesTotalPrice,
            'customTotalPrice' => $normalizedCustomTotalPrice,
            'totalPrice' => $normalizedCustomTotalPrice ?? $servicesTotalPrice,
            'currency' => $currency,
        ];
    }
}

