<?php

namespace KimaiPlugin\HolidayBundle\Tests\Entity;

use KimaiPlugin\HolidayBundle\Enum\AbsenceStatus;
use KimaiPlugin\HolidayBundle\Tests\Support\UserBuilder;
use KimaiPlugin\HolidayBundle\Tests\Support\WorkdayShiftBuilder;
use PHPUnit\Framework\TestCase;

final class WorkdayShiftTest extends TestCase
{
    public function testCoversReportsWhichSideMatches(): void
    {
        $shift = WorkdayShiftBuilder::requested(UserBuilder::make()->build(), '2026-10-27', '2026-10-29');

        $this->assertSame('from', $shift->covers(new \DateTimeImmutable('2026-10-27 15:00')));
        $this->assertSame('to', $shift->covers(new \DateTimeImmutable('2026-10-29')));
        $this->assertNull($shift->covers(new \DateTimeImmutable('2026-10-28')));
    }

    public function testIsActiveUnlessRejected(): void
    {
        $user = UserBuilder::make()->build();

        $this->assertTrue(WorkdayShiftBuilder::requested($user, '2026-10-27', '2026-10-29')->isActive());
        $this->assertTrue(WorkdayShiftBuilder::approved($user, '2026-10-27', '2026-10-29')->isActive());
        $this->assertFalse(WorkdayShiftBuilder::rejected($user, '2026-10-27', '2026-10-29')->isActive());
    }

    public function testStatusDefaultsToRequested(): void
    {
        $this->assertSame(AbsenceStatus::REQUESTED, (new \KimaiPlugin\HolidayBundle\Entity\WorkdayShift())->getStatus());
    }
}
