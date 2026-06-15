<?php

namespace App\Booking\Application\Query\GetProviderBookingDetails;

use App\Provider\Domain\Repository\ProviderRepository;
use RuntimeException;

final class GetProviderBookingDetailsHandler
{
    public function __construct(
        private readonly ProviderRepository $providers,
        private readonly ProviderBookingDetailsReader $readModel,
    ) {
    }

    /**
     * @return array{booking: array<string, mixed>}
     */
    public function __invoke(GetProviderBookingDetailsQuery $query): array
    {
        try {
            $provider = $this->providers->byId($query->providerId);
        } catch (\Throwable) {
            throw new RuntimeException('provider_not_found');
        }

        if ((string) $provider->getOwnerId() !== (string) $query->ownerUserId) {
            throw new RuntimeException('forbidden');
        }

        $payload = $this->readModel->getProviderBookingDetails($provider, $query->bookingId);
        if ($payload === null) {
            throw new RuntimeException('booking_not_found');
        }

        return $payload;
    }
}
