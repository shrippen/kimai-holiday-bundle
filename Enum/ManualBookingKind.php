<?php

namespace KimaiPlugin\HolidayBundle\Enum;

enum ManualBookingKind: string
{
    case TIME = 'time';
    case HOLIDAY = 'holiday';

    /** Translation key for this kind. */
    public function label(): string
    {
        return 'booking.kind.' . $this->value;
    }
}
