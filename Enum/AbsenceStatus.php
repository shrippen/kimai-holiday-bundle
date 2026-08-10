<?php

namespace KimaiPlugin\HolidayBundle\Enum;

enum AbsenceStatus: string
{
    case NEW = 'new';
    case REQUESTED = 'requested';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    /** Translation key for this status. */
    public function label(): string
    {
        return 'absence.status.' . $this->value;
    }
}
