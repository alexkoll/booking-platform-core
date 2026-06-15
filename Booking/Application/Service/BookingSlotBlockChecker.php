<?php

namespace App\Booking\Application\Service;

use App\Provider\Domain\Repository\ProviderSlotBlockRepository;
use App\Provider\Domain\ValueObject\EmployeeId;
use App\Provider\Domain\ValueObject\ProviderId;
use App\Provider\Domain\ValueObject\ProviderLocationId;

final class BookingSlotBlockChecker
{
    public function __construct(private readonly ProviderSlotBlockRepository $slotBlocks)
    {
    }

    /**
     * @return array<string, true>
     */
    public function blockedSet(ProviderId $providerId, ProviderLocationId $addressId, string $date, ?EmployeeId $employeeId): array
    {
        $blockedSet = [];
        foreach ($this->slotBlocks->findByProviderAndDate($providerId, $date) as $block) {
            if ((string) $block->getLocationId() !== (string) $addressId) {
                continue;
            }
            $blockEmployeeId = $block->getEmployeeId();
            if ($employeeId) {
                if ($blockEmployeeId && (string) $blockEmployeeId !== (string) $employeeId) {
                    continue;
                }
            } elseif ($blockEmployeeId) {
                continue;
            }
            $blockedSet[$block->getSlotTime()] = true;
        }

        return $blockedSet;
    }
}
