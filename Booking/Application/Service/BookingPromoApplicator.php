<?php

namespace App\Booking\Application\Service;

use RuntimeException;

final class BookingPromoApplicator
{
    public function applySelectedPromos(array $snapshot, array $promoItems, array $selectedPromoIds): array
    {
        if (!$selectedPromoIds) {
            return $snapshot;
        }

        $promoMap = [];
        foreach ($promoItems as $promo) {
            $promoMap[(string) $promo->getId()] = $promo;
        }

        $snapshotById = [];
        foreach ($snapshot as $index => $item) {
            $serviceId = $item['id'] ?? $item['employeeServiceId'] ?? null;
            if ($serviceId) {
                $snapshotById[(string) $serviceId] = $index;
            }
        }

        $appliedServices = [];
        foreach ($selectedPromoIds as $promoId) {
            if (!isset($promoMap[$promoId])) {
                throw new RuntimeException('Invalid promo');
            }
            $promo = $promoMap[$promoId];
            $serviceId = (string) $promo->getServiceId();
            if (isset($appliedServices[$serviceId])) {
                continue;
            }
            if (!isset($snapshotById[$serviceId])) {
                throw new RuntimeException('Invalid promo');
            }
            $index = $snapshotById[$serviceId];
            $price = $snapshot[$index]['price'] ?? null;
            if (!is_numeric($price)) {
                throw new RuntimeException('Invalid promo');
            }
            $discount = (float) $promo->getDiscountPercent();
            if ($discount <= 0 || $discount > 100) {
                continue;
            }
            $priceOriginal = (float) $price;
            $priceDiscounted = round($priceOriginal * ((100 - $discount) / 100), 2);
            if ($priceDiscounted < 0) {
                $priceDiscounted = 0.0;
            }
            $snapshot[$index]['priceOriginal'] = $priceOriginal;
            $snapshot[$index]['priceDiscountPercent'] = $discount;
            $snapshot[$index]['price'] = $priceDiscounted;
            $snapshot[$index]['promoId'] = $promoId;
            $snapshot[$index]['promoDate'] = $promo->getPromoDateKey();
            $appliedServices[$serviceId] = true;
        }

        return $snapshot;
    }
}
