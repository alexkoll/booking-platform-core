<?php

namespace App\Booking\Infrastructure\ReadModel;

use App\Booking\Application\Query\BookingReadSideReader;
use App\Booking\Application\Query\GetCurrentUserBookings\CurrentUserBookingsReader;
use App\Booking\Application\Query\GetProviderBookingDetails\ProviderBookingDetailsReader;
use App\Booking\Application\Query\GetProviderBookings\ProviderBookingsReader;
use App\Booking\Application\Support\BookingTotalsView;
use App\Booking\Domain\Entity\Booking;
use App\Booking\Domain\ValueObject\BookingId;
use App\Booking\Infrastructure\Doctrine\Entity\BookingDoctrine;
use App\Identity\Domain\ValueObject\UserId;
use App\Identity\Infrastructure\Doctrine\Entity\UserDoctrine;
use App\Identity\Infrastructure\Doctrine\Entity\UserProfileDoctrine;
use App\Payment\Domain\Entity\ProviderCommercialTerms;
use App\Payment\Infrastructure\Doctrine\Entity\PaymentDoctrine;
use App\Payment\Infrastructure\Doctrine\Entity\ProviderCommercialTermsDoctrine;
use App\Provider\Domain\Entity\Provider;
use App\Provider\Domain\Entity\ServiceType;
use App\Provider\Domain\ValueObject\EmployeeId;
use App\Provider\Domain\ValueObject\ProviderId;
use App\Provider\Infrastructure\Doctrine\Entity\ProviderDoctrine;
use App\Provider\Infrastructure\Doctrine\Entity\ProviderEmployeeServiceDoctrine;
use App\Provider\Infrastructure\Doctrine\Entity\ProviderLocationDoctrine;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;


final class DoctrineBookingCompatibilityReader extends DoctrineBookingReadHelper
{
    public function listBookingsByUserForApi(UserId $userId): array
    {
        $entities = $this->em->getRepository(BookingDoctrine::class)
            ->findBy(['userId' => (string) $userId], ['createdAt' => 'DESC']);

        return array_map(
            fn(BookingDoctrine $entity): array => $this->mapLegacyBooking($entity->toDomain(), false),
            $entities
        );
    }

    public function listBookingsByProviderForApi(ProviderId $providerId): array
    {
        $entities = $this->em->getRepository(BookingDoctrine::class)
            ->findBy(['providerId' => (string) $providerId], ['createdAt' => 'DESC']);

        return array_map(
            fn(BookingDoctrine $entity): array => $this->mapLegacyBooking($entity->toDomain(), true),
            $entities
        );
    }
}
