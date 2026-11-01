<?php

namespace KimaiPlugin\HolidayBundle\Tests\Service;

use KimaiPlugin\HolidayBundle\Entity\Absence;
use KimaiPlugin\HolidayBundle\Entity\PublicHoliday;
use KimaiPlugin\HolidayBundle\Enum\AbsenceType;
use KimaiPlugin\HolidayBundle\Enum\CalculationMode;
use KimaiPlugin\HolidayBundle\Service\DayBalance;
use KimaiPlugin\HolidayBundle\Service\DayExpectation;
use PHPUnit\Framework\TestCase;

final class DayExpectationTest extends TestCase
{
    private const BASE = 8 * 3600;

    public function testVacationCompensate(): void
    {
        $b = $this->eval(absence: $this->absence(AbsenceType::VACATION), absenceMode: CalculationMode::COMPENSATE);

        self::assertSame(self::BASE, $b->credit);
        self::assertSame(self::BASE, $b->expected);
        self::assertSame(self::BASE, $b->absenceSeconds);
    }

    public function testVacationReduce(): void
    {
        $b = $this->eval(absence: $this->absence(AbsenceType::VACATION), absenceMode: CalculationMode::REDUCE);

        self::assertSame(0, $b->credit);
        self::assertSame(0, $b->expected);
    }

    public function testHalfVacationCompensate(): void
    {
        $b = $this->eval(absence: $this->absence(AbsenceType::VACATION, halfDay: true), absenceMode: CalculationMode::COMPENSATE);

        self::assertSame(self::BASE / 2, $b->credit);
        self::assertSame(self::BASE, $b->expected);
    }

    public function testSicknessCountsOnlyMissingTime(): void
    {
        $b = $this->eval(timesheet: 3 * 3600, absence: $this->absence(AbsenceType::SICKNESS), absenceMode: CalculationMode::COMPENSATE);

        self::assertSame(5 * 3600, $b->credit);
    }

    public function testOtherUsesDuration(): void
    {
        $b = $this->eval(absence: $this->absence(AbsenceType::OTHER, duration: 2 * 3600), absenceMode: CalculationMode::COMPENSATE);

        self::assertSame(2 * 3600, $b->credit);
    }

    public function testFullHolidayReduce(): void
    {
        $b = $this->eval(holiday: $this->holiday(false), holidayMode: CalculationMode::REDUCE);

        self::assertSame(0, $b->expected);
        self::assertSame(0, $b->credit);
        self::assertSame(self::BASE, $b->holidaySeconds);
    }

    public function testHalfHolidayAndHalfVacationAreBothRelativeToBase(): void
    {
        $b = $this->eval(
            absence: $this->absence(AbsenceType::VACATION, halfDay: true),
            holiday: $this->holiday(true),
            absenceMode: CalculationMode::COMPENSATE,
            holidayMode: CalculationMode::COMPENSATE,
        );

        self::assertSame(self::BASE, $b->credit);
        self::assertSame(self::BASE / 2, $b->absenceSeconds);
        self::assertSame(self::BASE / 2, $b->holidaySeconds);
        self::assertSame(self::BASE, $b->expected);
    }

    public function testHalfHolidayAndHalfVacationReduce(): void
    {
        $b = $this->eval(
            absence: $this->absence(AbsenceType::VACATION, halfDay: true),
            holiday: $this->holiday(true),
            absenceMode: CalculationMode::REDUCE,
            holidayMode: CalculationMode::REDUCE,
        );

        self::assertSame(0, $b->expected);
    }

    public function testTimeOffIsMarkerOnly(): void
    {
        $b = $this->eval(absence: $this->absence(AbsenceType::TIME_OFF), absenceMode: CalculationMode::REDUCE);

        self::assertSame(self::BASE, $b->expected);
        self::assertSame(0, $b->credit);
        self::assertSame(0, $b->absenceSeconds);
    }

    public function testAbsenceWithTimesheetIsNotCountedTwice(): void
    {
        $b = $this->eval(absence: $this->absence(AbsenceType::VACATION), hasTimesheet: true, absenceMode: CalculationMode::REDUCE);

        self::assertSame(self::BASE, $b->expected);
        self::assertSame(0, $b->credit);
    }

    public function testNoBaseNoHoliday(): void
    {
        $b = (new DayExpectation())->evaluate(0, 0, null, false, $this->holiday(false), CalculationMode::COMPENSATE, CalculationMode::COMPENSATE);

        self::assertSame(0, $b->credit);
        self::assertSame(0, $b->holidaySeconds);
    }

    private function eval(
        int $timesheet = 0,
        ?Absence $absence = null,
        bool $hasTimesheet = false,
        ?PublicHoliday $holiday = null,
        CalculationMode $absenceMode = CalculationMode::COMPENSATE,
        CalculationMode $holidayMode = CalculationMode::COMPENSATE,
    ): DayBalance {
        return (new DayExpectation())->evaluate(self::BASE, $timesheet, $absence, $hasTimesheet, $holiday, $absenceMode, $holidayMode);
    }

    private function absence(AbsenceType $type, bool $halfDay = false, ?int $duration = null): Absence
    {
        return (new Absence())->setType($type)->setHalfDay($halfDay)->setDuration($duration);
    }

    private function holiday(bool $half): PublicHoliday
    {
        return (new PublicHoliday())->setName('Feiertag')->setHalfDay($half);
    }
}
