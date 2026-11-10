<?php

namespace KimaiPlugin\HolidayBundle\Service;

use App\Entity\User;
use App\WorkingTime\Mode\WorkingTimeModeFactory;
use KimaiPlugin\HolidayBundle\Entity\PublicHolidayGroup;
use KimaiPlugin\HolidayBundle\Enum\ProRata;
use KimaiPlugin\HolidayBundle\Repository\PublicHolidayGroupRepository;

/**
 * Reads work-contract data from Kimai's built-in user preferences
 * (profile → Arbeitsvertrag), not from a parallel plugin entity.
 */
class UserWorkContract
{
    public function __construct(
        private readonly WorkingTimeModeFactory $modeFactory,
        private readonly PublicHolidayGroupRepository $groupRepository,
        private readonly ?WorkdayShiftOverrides $overrides = null,
    ) {
    }

    /**
     * Expected seconds incl. approved workday shifts: origin day 0, target day takes the origin's hours.
     */
    public function getExpectedSecondsForDate(User $user, \DateTimeInterface $date): int
    {
        if ($this->overrides === null) {
            return $this->getContractSecondsForDate($user, $date);
        }

        if ($this->overrides->isShiftedAway($user, $date)) {
            return 0;
        }

        $source = $this->overrides->sourceFor($user, $date);
        if ($source === null) {
            return $this->getContractSecondsForDate($user, $date);
        }

        // A target outside the contract period gets nothing (the validation forbids it).
        if (!$this->isInContractPeriod($user, $date)) {
            return 0;
        }

        return $this->getContractSecondsForDate($user, $source);
    }

    /**
     * Raw contract hours of the day (weekday, contract start/end), ignoring shifts.
     */
    public function getContractSecondsForDate(User $user, \DateTimeInterface $date): int
    {
        $day = $date instanceof \DateTimeImmutable
            ? $date
            : \DateTimeImmutable::createFromInterface($date);

        $start = $user->getWorkStartingDay();
        if ($start !== null) {
            $startDay = \DateTimeImmutable::createFromInterface($start)->setTime(0, 0);
            if ($day < $startDay) {
                return 0;
            }
        }

        $end = $user->getLastWorkingDay();
        if ($end !== null) {
            $endDay = \DateTimeImmutable::createFromInterface($end)->setTime(0, 0);
            if ($day > $endDay) {
                return 0;
            }
        }

        $mode = $this->modeFactory->getMode($user->getWorkContractMode());

        return $mode->getCalculator($user)->getWorkHoursForDay($day);
    }

    private function isInContractPeriod(User $user, \DateTimeInterface $date): bool
    {
        $ymd = $date->format('Y-m-d');
        $start = $user->getWorkStartingDay()?->format('Y-m-d');
        $end = $user->getLastWorkingDay()?->format('Y-m-d');

        return !(($start !== null && $ymd < $start) || ($end !== null && $ymd > $end));
    }

    /**
     * Full entitlement for the vacation year. A contract that starts or ends inside the window gets
     * 1/12 per full month of employment (months counted from the window start), rounded up to half days.
     */
    public function getVacationDaysForYear(User $user, VacationYear $year): float
    {
        $perYear = (float) $user->getHolidaysPerYear();
        if ($perYear <= 0) {
            return $perYear;
        }

        $start = $user->getWorkStartingDay();
        $end = $user->getLastWorkingDay();
        $startDay = $start?->format('Y-m-d');
        $endDay = $end?->format('Y-m-d');

        if (($startDay === null || $startDay <= $year->start->format('Y-m-d')) && ($endDay === null || $endDay >= $year->end->format('Y-m-d'))) {
            return $perYear;
        }

        if ($year->proRata === ProRata::DAYS) {
            return $this->proRataByDays($perYear, $year, $start, $end);
        }

        $months = 0;
        for ($month = 0; $month < 12; ++$month) {
            $first = $this->addMonths($year->start, $month);
            $last = $this->addMonths($year->start, $month + 1)->modify('-1 day');
            if (($startDay === null || $startDay <= $first->format('Y-m-d')) && ($endDay === null || $endDay >= $last->format('Y-m-d'))) {
                ++$months;
            }
        }

        return ceil($perYear * $months / 12 * 2) / 2;
    }

    /**
     * Share of the employed days in the year, rounded up to half days.
     * Example: 20 days, employed 15.07.–31.12.2026 (170 of 365 days) → 9.5.
     */
    private function proRataByDays(float $perYear, VacationYear $year, ?\DateTimeInterface $start, ?\DateTimeInterface $end): float
    {
        $from = $start !== null && $start > $year->start ? \DateTimeImmutable::createFromInterface($start)->setTime(0, 0) : $year->start;
        $to = $end !== null && $end < $year->end ? \DateTimeImmutable::createFromInterface($end)->setTime(0, 0) : $year->end;
        if ($from > $to) {
            return 0.0;
        }

        $employed = $from->diff($to)->days + 1;
        $total = $year->start->diff($year->end)->days + 1;

        // round() first, so 20 × 365 / 365 stays 20 and is not pushed to 20.5 by float noise
        return ceil(round($perYear * $employed / $total * 2, 6)) / 2;
    }

    /**
     * Same day-of-month n months later, clamped to the month end (31.01. + 1 month = 28.02.).
     */
    private function addMonths(\DateTimeImmutable $date, int $months): \DateTimeImmutable
    {
        $first = $date->modify('first day of this month')->modify(sprintf('+%d months', $months));
        $day = min((int) $date->format('j'), (int) $first->format('t'));

        return $first->setDate((int) $first->format('Y'), (int) $first->format('n'), $day);
    }

    public function getPublicHolidayGroup(User $user): ?PublicHolidayGroup
    {
        $id = $user->getPublicHolidayGroup();
        if ($id === null || $id === '') {
            return null;
        }

        return $this->groupRepository->find((int) $id);
    }

    public function hasWorkingTimeConfigured(User $user): bool
    {
        return method_exists($user, 'hasWorkHourConfiguration')
            ? $user->hasWorkHourConfiguration()
            : $user->getWorkContractMode() !== 'none';
    }
}
