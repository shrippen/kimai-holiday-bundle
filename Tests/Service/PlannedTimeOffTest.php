<?php

namespace KimaiPlugin\HolidayBundle\Tests\Service;

use App\Entity\WorkingTime;
use App\WorkingTime\Model\DayAddon;
use App\WorkingTime\Model\Year;
use KimaiPlugin\HolidayBundle\Enum\AbsenceType;
use KimaiPlugin\HolidayBundle\Service\PlannedTimeOff;
use KimaiPlugin\HolidayBundle\Tests\Support\UserBuilder;
use PHPUnit\Framework\TestCase;

final class PlannedTimeOffTest extends TestCase
{
    private const HOUR = 3600;

    public function testSumsTimeOffAfterUntil(): void
    {
        $year = $this->year();
        $this->addon($year, '2026-11-13', AbsenceType::TIME_OFF->icon(), 10 * self::HOUR);
        $this->addon($year, '2026-12-23', AbsenceType::TIME_OFF->icon(), 7 * self::HOUR);

        self::assertSame(17 * self::HOUR, (new PlannedTimeOff())->secondsAfter($year, new \DateTimeImmutable('2026-11-10 12:00')));
    }

    public function testPastAndTodayAreAlreadyInTheBalance(): void
    {
        $year = $this->year();
        $this->addon($year, '2026-11-04', AbsenceType::TIME_OFF->icon(), 8 * self::HOUR);
        $this->addon($year, '2026-11-10', AbsenceType::TIME_OFF->icon(), 8 * self::HOUR);

        self::assertSame(0, (new PlannedTimeOff())->secondsAfter($year, new \DateTimeImmutable('2026-11-10 12:00')));
    }

    public function testOtherAbsencesDoNotCount(): void
    {
        $year = $this->year();
        $this->addon($year, '2026-11-11', AbsenceType::VACATION->icon(), 7 * self::HOUR);

        self::assertSame(0, (new PlannedTimeOff())->secondsAfter($year, new \DateTimeImmutable('2026-11-10 12:00')));
    }

    private function year(): Year
    {
        return new Year(new \DateTimeImmutable('2026-01-01'), UserBuilder::make()->build());
    }

    private function addon(Year $year, string $ymd, string $type, int $seconds): void
    {
        $date = new \DateTimeImmutable($ymd);
        $day = $year->getDay($date);
        $day->setWorkingTime(new WorkingTime($year->getUser(), $date));
        $day->addAddon(new DayAddon('title', 0, $seconds, $type));
    }
}
