<?php

namespace KimaiPlugin\HolidayBundle\Tests\Support;

use App\Entity\User;
use KimaiPlugin\HolidayBundle\Entity\WorkdayShift;
use KimaiPlugin\HolidayBundle\Enum\AbsenceStatus;

/** Builds WorkdayShift objects for unit tests. */
final class WorkdayShiftBuilder
{
    public static function approved(User $user, string $fromYmd, string $toYmd): WorkdayShift
    {
        return self::make($user, $fromYmd, $toYmd, AbsenceStatus::APPROVED);
    }

    public static function requested(User $user, string $fromYmd, string $toYmd): WorkdayShift
    {
        return self::make($user, $fromYmd, $toYmd, AbsenceStatus::REQUESTED);
    }

    public static function rejected(User $user, string $fromYmd, string $toYmd): WorkdayShift
    {
        return self::make($user, $fromYmd, $toYmd, AbsenceStatus::REJECTED);
    }

    /** Sets the (normally generated) id. */
    public static function id(WorkdayShift $shift, int $id): WorkdayShift
    {
        (new \ReflectionProperty(WorkdayShift::class, 'id'))->setValue($shift, $id);

        return $shift;
    }

    private static function make(User $user, string $fromYmd, string $toYmd, AbsenceStatus $status): WorkdayShift
    {
        return (new WorkdayShift())
            ->setUser($user)
            ->setFromDate(new \DateTimeImmutable($fromYmd))
            ->setToDate(new \DateTimeImmutable($toYmd))
            ->setStatus($status);
    }
}
