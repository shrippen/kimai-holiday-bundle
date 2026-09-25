<?php

namespace KimaiPlugin\HolidayBundle\Service;

use App\Entity\User;
use App\WorkingTime\Mode\WorkingTimeModeFactory;
use KimaiPlugin\HolidayBundle\Entity\PublicHolidayGroup;
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
    ) {
    }

    public function getExpectedSecondsForDate(User $user, \DateTimeInterface $date): int
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

    /**
     * Yearly vacation entitlement. With $year, it is pro-rated for a contract that starts or ends
     * within that year: 1/12 per full calendar month of employment, rounded up to half days.
     */
    public function getVacationDaysPerYear(User $user, ?int $year = null): float
    {
        $perYear = (float) $user->getHolidaysPerYear();
        if ($year === null || $perYear <= 0) {
            return $perYear;
        }

        $start = $user->getWorkStartingDay();
        $end = $user->getLastWorkingDay();
        $startDay = $start !== null ? $start->format('Y-m-d') : null;
        $endDay = $end !== null ? $end->format('Y-m-d') : null;

        if (($startDay === null || $startDay <= sprintf('%d-01-01', $year)) && ($endDay === null || $endDay >= sprintf('%d-12-31', $year))) {
            return $perYear;
        }

        $months = 0;
        for ($month = 1; $month <= 12; ++$month) {
            $first = new \DateTimeImmutable(sprintf('%d-%02d-01', $year, $month));
            if (($startDay === null || $startDay <= $first->format('Y-m-d')) && ($endDay === null || $endDay >= $first->format('Y-m-t'))) {
                ++$months;
            }
        }

        return ceil($perYear * $months / 12 * 2) / 2;
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
