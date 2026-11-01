<?php

namespace KimaiPlugin\HolidayBundle\Enum;

enum PresenceKind: string
{
    case FIXED = 'fixed';
    case SHIFTED = 'shifted';
    case EXTRA_SHIFT = 'extra_shift';

    /** Translation key for this kind. */
    public function label(): string
    {
        return 'holiday.availability.kind.' . $this->value;
    }
}
