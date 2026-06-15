<?php

namespace App\Booking\UI\Http;

use App\Booking\Application\Query\GetCurrentUserBookings\GetCurrentUserBookingsHandler;
use App\Booking\Application\Query\GetCurrentUserBookings\GetCurrentUserBookingsQuery;
use App\Booking\Domain\ValueObject\BookingStatus;
use App\Identity\Domain\ValueObject\UserId;
use App\Provider\Domain\ValueObject\ProviderId;
use App\Shared\Http\ApiResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

final class GetCurrentUserBookingsController extends AbstractController
{
    #[Route('/api/bookings/me', name: 'bookings_me', methods: ['GET'])]
    public function __invoke(Request $request, GetCurrentUserBookingsHandler $handler): JsonResponse
    {
        $user = $this->getUser();
        if (!$user || !method_exists($user, 'getId')) {
            throw new AuthenticationException();
        }

        $period = $this->normalizePeriod($request);
        if ($period === false) {
            return ApiResponse::error('Invalid period');
        }

        $dateFrom = (string) $request->query->get('from', '');
        $dateTo = (string) $request->query->get('to', '');
        if (($period === null || $period === 'all') && $dateFrom !== '' && !$this->isValidDate($dateFrom)) {
            return ApiResponse::error('from must be in YYYY-MM-DD format');
        }
        if (($period === null || $period === 'all') && $dateTo !== '' && !$this->isValidDate($dateTo)) {
            return ApiResponse::error('to must be in YYYY-MM-DD format');
        }

        $status = (string) $request->query->get('status', '');
        if ($status !== '') {
            try {
                BookingStatus::assertValid($status);
            } catch (\Throwable) {
                return ApiResponse::error('Invalid status');
            }
        }

        $providerId = null;
        $providerIdParam = (string) $request->query->get('providerId', '');
        if ($providerIdParam !== '') {
            try {
                $providerId = ProviderId::fromString($providerIdParam);
            } catch (\Throwable) {
                return ApiResponse::error('Invalid providerId');
            }
        }

        return ApiResponse::success($handler(new GetCurrentUserBookingsQuery(
            UserId::fromString((string) $user->getId()),
            max(1, (int) $request->query->get('page', 1)),
            max(1, (int) $request->query->get('limit', 10)),
            $providerId,
            $dateFrom !== '' ? $dateFrom : null,
            $dateTo !== '' ? $dateTo : null,
            $status !== '' ? $status : null,
            $period,
        )));
    }

    private function isValidDate(string $value): bool
    {
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        return $dt && $dt->format('Y-m-d') === $value;
    }

    private function normalizePeriod(Request $request): string|false|null
    {
        if (!$request->query->has('period')) {
            return null;
        }

        $period = strtolower(trim((string) $request->query->get('period', '')));
        if (!in_array($period, ['today', 'future', 'past', 'all'], true)) {
            return false;
        }

        return $period;
    }
}
