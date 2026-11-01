<?php

namespace KimaiPlugin\HolidayBundle\Service;

use App\Entity\User;
use KimaiPlugin\HolidayBundle\Entity\Absence;
use KimaiPlugin\HolidayBundle\Entity\WorkdayShift;

/** Presence of a location's members on one calendar day. */
final class AvailabilityDay
{
    public function __construct(
        public readonly \DateTimeImmutable $date,
        public readonly OpeningSlot $slot,
        /** @var PresenceEntry[] */
        public readonly array $present,
        /** @var array<array{user: User, absence: Absence}> */
        public readonly array $absent,
        /** @var array<array{user: User, absence?: Absence, shift?: WorkdayShift}> */
        public readonly array $pending,
    ) {
    }

    public function isOpen(): bool
    {
        return $this->slot->open;
    }

    public function isUnstaffed(): bool
    {
        return $this->isOpen() && $this->present === [];
    }
}
