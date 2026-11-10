<?php

namespace KimaiPlugin\HolidayBundle\Service;

use App\Entity\User;
use KimaiPlugin\HolidayBundle\Enum\ProRata;
use KimaiPlugin\HolidayBundle\Enum\VacationYearMode;

/**
 * Vacation year = employment year starting on the anniversary of the contract start date
 * (calendar year when the user has no start date). With the setting "calendar year" every
 * vacation year is a calendar year, the entry year pro rata by days.
 */
class VacationYearResolver
{
    public function __construct(private readonly ?HolidayConfiguration $configuration = null)
    {
    }

    public function isCalendarFallback(User $user): bool
    {
        return $user->getWorkStartingDay() === null;
    }

    public function forDate(User $user, \DateTimeInterface $date): VacationYear
    {
        $anchor = $this->anchor($user);
        if ($anchor === null) {
            return $this->calendarYear((int) $date->format('Y'));
        }

        $day = $date->format('Y-m-d');
        $index = (int) $date->format('Y') - (int) $anchor->format('Y');
        if ($this->startOf($anchor, $index)->format('Y-m-d') > $day) {
            --$index;
        }

        return $this->forIndex($user, $index);
    }

    /**
     * Route parameter → vacation year. No value: the year containing $today. Without a start date only
     * calendar years (>= 1000) are valid. With a start date a small value is the index; a calendar year
     * (>= 1000, older links) maps to the vacation year starting in that calendar year.
     */
    public function fromRouteParam(User $user, ?int $param, ?\DateTimeInterface $today = null): VacationYear
    {
        $today ??= new \DateTimeImmutable('today');
        if ($param === null) {
            return $this->forDate($user, $today);
        }

        if ($this->anchor($user) === null) {
            return $param >= 1000 ? $this->calendarYear($param) : $this->forDate($user, $today);
        }
        if ($param < 1000) {
            return $this->forIndex($user, $param);
        }

        return $this->forIndex($user, $param - (int) $this->forIndex($user, 0)->start->format('Y'));
    }

    /**
     * Index below 0 (before the contract start) falls into the first year.
     */
    public function forIndex(User $user, int $index): VacationYear
    {
        $anchor = $this->anchor($user);
        if ($anchor === null) {
            return $this->calendarYear($index);
        }

        $index = max(0, $index);

        return new VacationYear(
            $this->startOf($anchor, $index),
            $this->startOf($anchor, $index + 1)->modify('-1 day'),
            $index
        );
    }

    /**
     * Null before the contract start. Without a start date the calendar year before.
     */
    public function previous(User $user, VacationYear $year): ?VacationYear
    {
        if ($this->isCalendarMode()) {
            // chain from the entry year on, no earlier
            $start = $user->getWorkStartingDay();

            return $start !== null && $year->index <= (int) $start->format('Y') ? null : $this->calendarYear($year->index - 1);
        }
        if ($this->anchor($user) === null) {
            return $this->calendarYear($year->index - 1);
        }

        return $year->index <= 0 ? null : $this->forIndex($user, $year->index - 1);
    }

    public function next(User $user, VacationYear $year): VacationYear
    {
        return $this->forIndex($user, $year->index + 1);
    }

    private function isCalendarMode(): bool
    {
        return $this->configuration?->getVacationYearMode() === VacationYearMode::CALENDAR;
    }

    /**
     * Start of the employment years; null without start date or in calendar mode.
     */
    private function anchor(User $user): ?\DateTimeImmutable
    {
        if ($this->isCalendarMode()) {
            return null;
        }
        $start = $user->getWorkStartingDay();

        return $start === null ? null : \DateTimeImmutable::createFromInterface($start)->setTime(0, 0);
    }

    /**
     * Anniversary in the year anchor+index; 29.02. becomes the last day of February in other years.
     */
    private function startOf(\DateTimeImmutable $anchor, int $index): \DateTimeImmutable
    {
        $year = (int) $anchor->format('Y') + $index;
        $month = (int) $anchor->format('n');
        $first = new \DateTimeImmutable(sprintf('%d-%02d-01', $year, $month));
        $day = min((int) $anchor->format('j'), (int) $first->format('t'));

        return $first->setDate($year, $month, $day);
    }

    private function calendarYear(int $year): VacationYear
    {
        return new VacationYear(
            new \DateTimeImmutable(sprintf('%d-01-01', $year)),
            new \DateTimeImmutable(sprintf('%d-12-31', $year)),
            $year,
            $this->isCalendarMode() ? ProRata::DAYS : ProRata::MONTHS
        );
    }
}
