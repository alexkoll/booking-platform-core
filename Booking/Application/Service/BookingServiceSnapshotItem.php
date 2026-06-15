<?php

namespace App\Booking\Application\Service;

final class BookingServiceSnapshotItem
{
    public function __construct(
        private readonly string $id,
        private readonly string $name,
        private readonly ?string $description,
        private readonly ?string $categoryId,
        private readonly ?string $category,
        private readonly float $price,
        private readonly ?string $currency,
        private readonly int $durationMinutes,
        private readonly ?string $employeeServiceId,
        private readonly ?string $addressId,
        private readonly ?string $employeeId = null,
        private readonly string $type = 'service',
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = [
            'id' => $this->id,
            'employeeServiceId' => $this->employeeServiceId,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'categoryId' => $this->categoryId,
            'category' => $this->category,
            'price' => $this->price,
            'currency' => $this->currency,
            'durationMinutes' => $this->durationMinutes,
        ];

        if ($this->addressId !== null) {
            $payload['addressId'] = $this->addressId;
        }
        if ($this->employeeId !== null) {
            $payload['employeeId'] = $this->employeeId;
        }

        return $payload;
    }
}
