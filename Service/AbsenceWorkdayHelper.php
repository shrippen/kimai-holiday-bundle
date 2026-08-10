<?php

namespace KimaiPlugin\HolidayBundle\Service;

use App\Entity\User;
use KimaiPlugin\HolidayBundle\Entity\Absence;

/**
 * Absence days only count on contractual workdays — never on weekends (Sat/Sun),
 * and never on days with 0 expected hours (e.g. configured non-working weekdays).
 */
class AbsenceWorkdayHelper
{
    public function __construct(private readonly UserWorkContract $userWorkContract)
    {
    }

    public function isWeekend(\DateTimeInterface $date): bool
    {
        $n = (int) ($date instanceof \DateTimeImmutable ? $date : \DateTimeImmutable::createFromInterface($date))->format('N');

        return $n >= 6;
    }

    /**
     * Whether an absence should apply on this calendar day (contracts, ICS, Urlaubskonto).
     */
    public function isAbsenceApplicableDay(User $user, \DateTimeInterface $date): bool
    {
        if ($this->isWeekend($date)) {
            return false;
        }

        return $this->userWorkContract->getExpectedSecondsForDate($user, $date) > 0;
    }

    /**
     * Workdays covered by the absence (half-day = 0.5 per applicable day).
     * Optionally limit to a calendar year for Urlaubskonto.
     */
    public function countDays(Absence $absence, ?int $year = null): float
    {
        $user = $absence->getUser();
        $cursor = $absence->getStartDate();
        $end = $absence->getEndDate();
        if ($user === null || $cursor === null || $end === null) {
            return 0.0;
        }

        $used = 0.0;
        while ($cursor <= $end) {
            if (($year === null || (int) $cursor->format('Y') === $year) && $this->isAbsenceApplicableDay($user, $cursor)) {
                $used += $absence->isHalfDay() ? 0.5 : 1.0;
            }
            $cursor = $cursor->modify('+1 day');
        }

        return $used;
    }

    /**
     * Contiguous applicable-day ranges as [start, end] inclusive dates (for ICS / calendar).
     *
     * @return list<array{0: \DateTimeImmutable, 1: \DateTimeImmutable}>
     */
    public function applicableRanges(Absence $absence): array
    {
        $user = $absence->getUser();
        $cursor = $absence->getStartDate();
        $end = $absence->getEndDate();
        if ($user === null || $cursor === null || $end === null) {
            return [];
        }

        $ranges = [];
        $rangeStart = null;
        $rangeEnd = null;

        while ($cursor <= $end) {
            if ($this->isAbsenceApplicableDay($user, $cursor)) {
                if ($rangeStart === null) {
                    $rangeStart = $cursor;
                }
                $rangeEnd = $cursor;
            } elseif ($rangeStart !== null && $rangeEnd !== null) {
                $ranges[] = [$rangeStart, $rangeEnd];
                $rangeStart = null;
                $rangeEnd = null;
            }
            $cursor = $cursor->modify('+1 day');
        }

        if ($rangeStart !== null && $rangeEnd !== null) {
            $ranges[] = [$rangeStart, $rangeEnd];
        }

        return $ranges;
    }
}
