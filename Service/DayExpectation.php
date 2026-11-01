<?php

namespace KimaiPlugin\HolidayBundle\Service;

use KimaiPlugin\HolidayBundle\Entity\Absence;
use KimaiPlugin\HolidayBundle\Entity\PublicHoliday;
use KimaiPlugin\HolidayBundle\Enum\AbsenceType;
use KimaiPlugin\HolidayBundle\Enum\CalculationMode;

/**
 * The one day rule for absences and public holidays. Absence and holiday
 * both count relative to the base (contract hours of the day).
 */
class DayExpectation
{
    public function evaluate(
        int $base,
        int $timesheetSeconds,
        ?Absence $absence,
        bool $absenceHasTimesheet,
        ?PublicHoliday $holiday,
        CalculationMode $absenceMode,
        CalculationMode $holidayMode,
    ): DayBalance {
        $absenceSeconds = $this->absenceSeconds($base, $timesheetSeconds, $absence, $absenceHasTimesheet);
        $holidaySeconds = $this->holidaySeconds($base, $holiday);

        $credit = 0;
        $reduce = 0;
        if ($absenceMode === CalculationMode::COMPENSATE) {
            $credit += $absenceSeconds;
        } else {
            $reduce += $absenceSeconds;
        }
        if ($holidayMode === CalculationMode::COMPENSATE) {
            $credit += $holidaySeconds;
        } else {
            $reduce += $holidaySeconds;
        }

        return new DayBalance(max(0, $base - $reduce), $credit, $absenceSeconds, $holidaySeconds);
    }

    private function absenceSeconds(int $base, int $timesheetSeconds, ?Absence $absence, bool $hasTimesheet): int
    {
        // A generated absence timesheet is already part of the worked time.
        if ($absence === null || $hasTimesheet || $absence->getType() === AbsenceType::TIME_OFF) {
            return 0;
        }

        if ($absence->getType() === AbsenceType::OTHER && $absence->getDuration() !== null) {
            return $absence->getDuration();
        }

        $target = $absence->isHalfDay() ? intdiv($base, 2) : $base;
        if (\in_array($absence->getType(), [AbsenceType::SICKNESS, AbsenceType::SICKNESS_RELATIVE], true)) {
            return max(0, $target - $timesheetSeconds);
        }

        return $target;
    }

    private function holidaySeconds(int $base, ?PublicHoliday $holiday): int
    {
        if ($holiday === null || $base <= 0) {
            return 0;
        }

        return $holiday->isHalfDay() ? intdiv($base, 2) : $base;
    }
}
