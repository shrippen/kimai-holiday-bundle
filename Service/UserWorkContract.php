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

    public function getVacationDaysPerYear(User $user): float
    {
        return $user->getHolidaysPerYear();
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
