<?php

namespace KimaiPlugin\HolidayBundle\Service;

/**
 * Result of the day rule: expected seconds after reductions, credited seconds
 * (compensate mode) and the absence and holiday amounts the day contains.
 */
final class DayBalance
{
    public function __construct(
        public readonly int $expected,
        public readonly int $credit,
        public readonly int $absenceSeconds,
        public readonly int $holidaySeconds,
    ) {
    }
}
