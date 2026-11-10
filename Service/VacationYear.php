<?php

namespace KimaiPlugin\HolidayBundle\Service;

use KimaiPlugin\HolidayBundle\Enum\ProRata;

/**
 * One vacation year: the employment year from the contract anniversary, or the calendar year
 * without a contract start date. $index is 0 for the first employment year, the year number
 * for the calendar fallback.
 */
final class VacationYear
{
    public function __construct(
        public readonly \DateTimeImmutable $start,
        public readonly \DateTimeImmutable $end,
        public readonly int $index,
        public readonly ProRata $proRata = ProRata::MONTHS,
    ) {
    }

    public function contains(\DateTimeInterface $date): bool
    {
        $day = $date->format('Y-m-d');

        return $day >= $this->start->format('Y-m-d') && $day <= $this->end->format('Y-m-d');
    }

    public function label(): string
    {
        return $this->start->format('d.m.Y') . ' – ' . $this->end->format('d.m.Y');
    }
}
