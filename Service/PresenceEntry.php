<?php

namespace KimaiPlugin\HolidayBundle\Service;

use App\Entity\User;
use KimaiPlugin\HolidayBundle\Entity\ExtraShift;
use KimaiPlugin\HolidayBundle\Entity\WorkdayShift;
use KimaiPlugin\HolidayBundle\Enum\PresenceKind;

/** One person present at a location on one day, and why. */
final class PresenceEntry
{
    public function __construct(
        public readonly User $user,
        public readonly PresenceKind $kind,
        public readonly bool $halfDay,
        public readonly ?ExtraShift $extraShift = null,
        public readonly ?WorkdayShift $shift = null,
    ) {
    }
}
