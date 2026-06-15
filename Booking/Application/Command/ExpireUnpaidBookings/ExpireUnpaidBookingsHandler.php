<?php

namespace App\Booking\Application\Command\ExpireUnpaidBookings;

use App\Booking\Domain\Repository\BookingRepository;
use App\Booking\Domain\ValueObject\BookingStatus;
use App\Provider\Infrastructure\Cache\PublicProviderPageBootstrapCacheInvalidator;

final class ExpireUnpaidBookingsHandler
{
    public function __construct(
        private readonly BookingRepository $bookings,
        private readonly PublicProviderPageBootstrapCacheInvalidator $publicPageCache,
    ) {
    }

    public function __invoke(int $limit = 200): int
    {
        $expired = $this->bookings->findExpiredPaymentBookings(new \DateTimeImmutable(), $limit);
        $count = 0;
        $providerIds = [];

        foreach ($expired as $booking) {
            if ($booking->getStatus() === BookingStatus::CANCELLED) {
                continue;
            }
            $booking->markPaymentFailed();
            $booking->expire();
            $this->bookings->add($booking);
            $providerIds[(string) $booking->getProviderId()] = true;
            $count++;
        }

        foreach (array_keys($providerIds) as $providerId) {
            $this->publicPageCache->invalidateProvider($providerId);
        }

        return $count;
    }
}
