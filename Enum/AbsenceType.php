<?php

namespace KimaiPlugin\HolidayBundle\Enum;

enum AbsenceType: string
{
    case VACATION = 'vacation';
    case SICKNESS = 'sickness';
    case SICKNESS_RELATIVE = 'sickness_relative';
    case TIME_OFF = 'time_off';
    case OTHER = 'other';

    public function requiresApproval(): bool
    {
        return match ($this) {
            self::SICKNESS, self::SICKNESS_RELATIVE => false,
            default => true,
        };
    }

    /** Translation key for this type. */
    public function label(): string
    {
        return 'absence.type.' . $this->value;
    }

    /**
     * Kimai / Tabler icon name (see config/packages/tabler.yaml icons).
     * Used as DayAddon type so Arbeitszeiten can render {{ type|icon }}.
     */
    public function icon(): string
    {
        return match ($this) {
            self::VACATION => 'holiday',
            self::SICKNESS, self::SICKNESS_RELATIVE => 'sickness',
            self::TIME_OFF => 'time-off',
            self::OTHER => 'other',
        };
    }
}
