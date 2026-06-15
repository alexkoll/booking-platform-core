<?php

namespace App\Booking\Application\Query\GetProviderBookings;

use App\I18n\Application\Service\TimezoneCatalog;
use App\Provider\Domain\Repository\ProviderRepository;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

final class GetProviderBookingsHandler
{
    public function __construct(
        private readonly ProviderRepository $providers,
        private readonly ProviderBookingsReader $readModel,
        private readonly TimezoneCatalog $timezoneCatalog,
    ) {
    }

    /**
     * @return array{providerType: string, employees: array<int, array<string, mixed>>, items: array<int, array<string, mixed>>, clients: array<int, array<string, mixed>>, pagination: array<string, int>}
     */
    public function __invoke(GetProviderBookingsQuery $query): array
    {
        $provider = $this->providers->byOwnerId($query->ownerUserId);
        if (!$provider) {
            throw new RuntimeException('Provider not found');
        }

        $employeeId = $provider->getType() === 'company' ? $query->employeeId : null;
        $dateFrom = $query->dateFrom;
        $dateTo = $query->dateTo;
        if ($query->period !== null && $query->period !== 'all') {
            [$dateFrom, $dateTo] = $this->dateRangeForPeriod($query->period, $this->resolveTimezone($provider->getTimezone()));
        }

        return $this->readModel->listProviderBookings(
            $provider,
            $query->page,
            $query->limit,
            $dateFrom,
            $dateTo,
            $query->status,
            $employeeId,
            $query->clientId
        );
    }

    private function resolveTimezone(?string $timezoneName): DateTimeZone
    {
        $timezoneName = trim((string) $timezoneName);
        if (!$this->timezoneCatalog->isSupportedTimezone($timezoneName)) {
            $timezoneName = $this->timezoneCatalog->defaultTimezone();
        }

        return new DateTimeZone($timezoneName);
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function dateRangeForPeriod(string $period, DateTimeZone $timezone): array
    {
        $today = new DateTimeImmutable('today', $timezone);

        return match ($period) {
            'today' => [$today->format('Y-m-d'), $today->format('Y-m-d')],
            'future' => [$today->modify('+1 day')->format('Y-m-d'), null],
            'past' => [null, $today->modify('-1 day')->format('Y-m-d')],
            default => [null, null],
        };
    }
}
