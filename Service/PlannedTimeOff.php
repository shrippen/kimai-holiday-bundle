<?php

namespace KimaiPlugin\HolidayBundle\Service;

use App\WorkingTime\Model\Year;
use KimaiPlugin\HolidayBundle\Enum\AbsenceType;

/**
 * Approved time off after "until": Kimai counts days only up to today, so these hours
 * are not in the balance yet. Reads the markers WorkingTimeYearSubscriber puts on the days.
 */
final class PlannedTimeOff
{
    public function secondsAfter(Year $year, \DateTimeInterface $until): int
    {
        $seconds = 0;

        foreach ($year->getMonths() as $month) {
            foreach ($month->getDays() as $day) {
                if ($day->getDay() <= $until) {
                    continue;
                }
                foreach ($day->getAddons() as $addon) {
                    if ($addon->getType() === AbsenceType::TIME_OFF->icon()) {
                        $seconds += $addon->getVisibleDuration();
                    }
                }
            }
        }

        return $seconds;
    }
}
