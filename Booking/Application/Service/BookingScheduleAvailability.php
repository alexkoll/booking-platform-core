<?php

namespace App\Booking\Application\Service;

use RuntimeException;
use Symfony\Contracts\Translation\TranslatorInterface;

final class BookingScheduleAvailability
{
    /**
     * @param array<int, array<string, mixed>> $scheduleRows
     * @return array{slotMinutes: int, startMinutes: int, allowedSet: array<string, true>}
     */
    public function assertFits(array $scheduleRows, string $time, int $durationMinutes, TranslatorInterface $translator): array
    {
        $context = $this->findScheduleSlotContext($scheduleRows, $time);
        if (!$context) {
            throw new RuntimeException($translator->trans('booking.slot_unavailable'));
        }

        $slotMinutes = $context['slotMinutes'];
        $segmentEnd = $context['segmentEnd'];
        $startMinutes = $this->timeToMinutes($time);
        if ($startMinutes === null) {
            throw new RuntimeException($translator->trans('booking.slot_unavailable'));
        }
        if ($startMinutes + $durationMinutes > $segmentEnd) {
            throw new RuntimeException($translator->trans('booking.slot_too_short'));
        }

        return [
            'slotMinutes' => $slotMinutes,
            'startMinutes' => $startMinutes,
            'allowedSet' => array_fill_keys($this->buildSlotsForDay($scheduleRows), true),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $scheduleRows
     * @return array{slotMinutes: int, segmentEnd: int}|null
     */
    private function findScheduleSlotContext(array $scheduleRows, string $time): ?array
    {
        $start = $this->timeToMinutes($time);
        if ($start === null) {
            return null;
        }

        foreach ($scheduleRows as $row) {
            $from = $this->timeToMinutes($row['timeFrom'] ?? null);
            $to = $this->timeToMinutes($row['timeTo'] ?? null);
            if ($from === null || $to === null || $start < $from || $start >= $to) {
                continue;
            }

            $slotMinutes = max(1, (int) ($row['slotMinutes'] ?? 30));
            $breakFrom = $this->timeToMinutes($row['breakFrom'] ?? null);
            $breakTo = $this->timeToMinutes($row['breakTo'] ?? null);
            if ($breakFrom !== null && $breakTo !== null) {
                if ($start >= $breakFrom && $start < $breakTo) {
                    return null;
                }
                if ($start < $breakFrom) {
                    return ['slotMinutes' => $slotMinutes, 'segmentEnd' => $breakFrom];
                }
            }

            return ['slotMinutes' => $slotMinutes, 'segmentEnd' => $to];
        }

        return null;
    }

    /**
     * @param array<int, array<string, mixed>> $scheduleRows
     * @return string[]
     */
    private function buildSlotsForDay(array $scheduleRows): array
    {
        $slots = [];
        foreach ($scheduleRows as $row) {
            $from = $this->timeToMinutes($row['timeFrom'] ?? null);
            $to = $this->timeToMinutes($row['timeTo'] ?? null);
            $slotMinutes = max(1, (int) ($row['slotMinutes'] ?? 30));
            if ($from === null || $to === null || $to <= $from) {
                continue;
            }
            $breakFrom = $this->timeToMinutes($row['breakFrom'] ?? null);
            $breakTo = $this->timeToMinutes($row['breakTo'] ?? null);
            for ($minute = $from; $minute + $slotMinutes <= $to; $minute += $slotMinutes) {
                if ($breakFrom !== null && $breakTo !== null && $minute >= $breakFrom && $minute < $breakTo) {
                    continue;
                }
                $slots[] = $this->minutesToTime($minute);
            }
        }

        return array_values(array_unique($slots));
    }

    private function timeToMinutes(mixed $time): ?int
    {
        if (!is_string($time) || $time === '') {
            return null;
        }
        $parts = explode(':', $time);
        if (count($parts) < 2) {
            return null;
        }

        return ((int) $parts[0] * 60) + (int) $parts[1];
    }

    private function minutesToTime(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
