<?php

namespace KimaiPlugin\HolidayBundle\Service;

use App\Entity\User;
use KimaiPlugin\HolidayBundle\Entity\Absence;
use KimaiPlugin\HolidayBundle\Repository\PublicHolidayRepository;

/**
 * Absence days only count on contractual workdays: the contract has expected hours
 * for that weekday (within contract start/end) and the day is no full-day public
 * holiday of the user's holiday group. No weekday is special-cased.
 */
class AbsenceWorkdayHelper
{
    private const HOLIDAY_FULL = 'full';
    private const HOLIDAY_HALF = 'half';

    /** @var array<string, array<string, string>> "groupId-year" => date => HOLIDAY_FULL|HOLIDAY_HALF */
    private array $publicHolidayCache = [];

    public function __construct(
        private readonly UserWorkContract $userWorkContract,
        private readonly PublicHolidayRepository $publicHolidayRepository,
    ) {
    }

    /**
     * Whether the contract expects hours on this day (weekday hours, contract start/end).
     */
    public function hasContractHours(User $user, \DateTimeInterface $date): bool
    {
        return $this->userWorkContract->getExpectedSecondsForDate($user, $date) > 0;
    }

    /**
     * No contract hours or a full-day public holiday.
     */
    public function isNonWorkingDay(User $user, \DateTimeInterface $date): bool
    {
        return !$this->hasContractHours($user, $date) || $this->isFullDayPublicHoliday($user, $date);
    }

    /**
     * Whether an absence should apply on this calendar day (contracts, ICS, Urlaubskonto).
     */
    public function isAbsenceApplicableDay(User $user, \DateTimeInterface $date): bool
    {
        return !$this->isNonWorkingDay($user, $date);
    }

    public function isFullDayPublicHoliday(User $user, \DateTimeInterface $date): bool
    {
        return $this->holidayKind($user, $date) === self::HOLIDAY_FULL;
    }

    /** Full or half public holiday. */
    public function isPublicHoliday(User $user, \DateTimeInterface $date): bool
    {
        return $this->holidayKind($user, $date) !== null;
    }

    private function holidayKind(User $user, \DateTimeInterface $date): ?string
    {
        $group = $this->userWorkContract->getPublicHolidayGroup($user);
        if ($group === null || $group->getId() === null) {
            return null;
        }

        $year = (int) $date->format('Y');
        $cacheKey = $group->getId() . '-' . $year;
        if (!isset($this->publicHolidayCache[$cacheKey])) {
            $this->publicHolidayCache[$cacheKey] = [];
            foreach ($this->publicHolidayRepository->findByGroupAndYear($group, $year) as $holiday) {
                $day = $holiday->getDate()?->format('Y-m-d');
                if ($day !== null) {
                    $this->publicHolidayCache[$cacheKey][$day] = $holiday->isHalfDay() ? self::HOLIDAY_HALF : self::HOLIDAY_FULL;
                }
            }
        }

        return $this->publicHolidayCache[$cacheKey][$date->format('Y-m-d')] ?? null;
    }

    /**
     * Applicable days of the absence inside [$from, $to] (inclusive, null = unbounded).
     */
    public function countDaysBetween(Absence $absence, ?\DateTimeImmutable $from, ?\DateTimeImmutable $to): float
    {
        $user = $absence->getUser();
        $cursor = $absence->getStartDate();
        $end = $absence->getEndDate();
        if ($user === null || $cursor === null || $end === null) {
            return 0.0;
        }

        $used = 0.0;
        while ($cursor <= $end) {
            $inWindow = ($from === null || $cursor >= $from) && ($to === null || $cursor <= $to);
            if ($inWindow && $this->isAbsenceApplicableDay($user, $cursor)) {
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
