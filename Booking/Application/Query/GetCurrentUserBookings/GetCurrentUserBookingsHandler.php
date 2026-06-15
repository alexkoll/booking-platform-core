<?php

namespace App\Booking\Application\Query\GetCurrentUserBookings;

use App\I18n\Application\Service\TimezoneCatalog;
use App\Provider\Domain\Repository\ProviderRepository;
use DateTimeImmutable;
use DateTimeZone;

final class GetCurrentUserBookingsHandler
{
    public function __construct(
        private readonly CurrentUserBookingsReader $readModel,
        private readonly ProviderRepository $providers,
        private readonly TimezoneCatalog $timezoneCatalog,
    ) {
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, filters: array<string, mixed>, pagination: array<string, int>}
     */
    public function __invoke(GetCurrentUserBookingsQuery $query): array
    {
        $providerLocalDatesById = [];
        $dateFrom = $query->dateFrom;
        $dateTo = $query->dateTo;

        if ($query->period !== null && $query->period !== 'all') {
            $providerIds = $query->providerId
                ? [(string) $query->providerId]
                : $this->readModel->listProviderIdsForUser($query->userId);
            $providerLocalDatesById = $this->buildProviderLocalDates(
                $providerIds,
                $this->providers->timezonesByIds($providerIds)
            );
            $dateFrom = null;
            $dateTo = null;
        }

        return $this->readModel->listCurrentUserBookings(
            $query->userId,
            $query->page,
            $query->limit,
            $query->providerId,
            $dateFrom,
            $dateTo,
            $query->status,
            $query->period,
            $providerLocalDatesById
        );
    }

    /**
     * @param string[] $providerIds
     * @param array<string, string|null> $providerTimezonesById
     *
     * @return array<string, string>
     */
    private function buildProviderLocalDates(array $providerIds, array $providerTimezonesById): array
    {
        $result = [];
        foreach (array_values(array_unique(array_filter(array_map('strval', $providerIds)))) as $providerId) {
            $timezoneName = trim((string) ($providerTimezonesById[$providerId] ?? ''));
            if (!$this->timezoneCatalog->isSupportedTimezone($timezoneName)) {
                $timezoneName = $this->timezoneCatalog->defaultTimezone();
            }
            $result[$providerId] = (new DateTimeImmutable('today', new DateTimeZone($timezoneName)))->format('Y-m-d');
        }

        return $result;
    }
}
