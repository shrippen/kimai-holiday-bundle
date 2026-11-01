<?php

namespace KimaiPlugin\HolidayBundle\Tests\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use KimaiPlugin\HolidayBundle\Repository\AbsenceRepository;
use KimaiPlugin\HolidayBundle\Repository\WorkdayShiftRepository;
use KimaiPlugin\HolidayBundle\Service\ApproverAccess;
use KimaiPlugin\HolidayBundle\Service\ApproverResolver;
use KimaiPlugin\HolidayBundle\Service\OpenRequestCounter;
use KimaiPlugin\HolidayBundle\Tests\Support\AbsenceBuilder;
use KimaiPlugin\HolidayBundle\Tests\Support\UserBuilder;
use KimaiPlugin\HolidayBundle\Tests\Support\WorkdayShiftBuilder;
use PHPUnit\Framework\TestCase;

final class OpenRequestCounterTest extends TestCase
{
    public function testSumsAbsencesAndShifts(): void
    {
        $boss = UserBuilder::make()->build();
        $employee = UserBuilder::make()->build();

        $users = $this->createStub(UserRepository::class);
        $users->method('findBy')->willReturn([$boss, $employee]);
        $access = $this->createStub(ApproverAccess::class);
        $access->method('mayApprove')->willReturn(true);
        $access->method('mayAccess')->willReturn(true);

        $absences = $this->createMock(AbsenceRepository::class);
        $absences->expects(self::once())->method('findRequestedForUsers')->with([$employee])
            ->willReturn([AbsenceBuilder::requested($employee, '2026-10-21', '2026-10-21')]);
        $shifts = $this->createMock(WorkdayShiftRepository::class);
        $shifts->expects(self::once())->method('findRequestedForUsers')->with([$employee])->willReturn([
            WorkdayShiftBuilder::requested($employee, '2026-10-30', '2026-10-29'),
            WorkdayShiftBuilder::requested($employee, '2026-11-06', '2026-11-05'),
        ]);

        $counter = new OpenRequestCounter(new ApproverResolver($users, $access), $absences, $shifts);

        self::assertSame(3, $counter->countFor($boss));
    }

    public function testBossWithOnlyShiftRightCountsShiftsOnly(): void
    {
        $boss = UserBuilder::make()->build();
        $employee = UserBuilder::make()->build();

        $users = $this->createStub(UserRepository::class);
        $users->method('findBy')->willReturn([$boss, $employee]);
        $access = $this->createStub(ApproverAccess::class);
        $access->method('mayApprove')->willReturnCallback(
            static fn (User $u, string $permission = ''): bool => $permission === 'approve_shift'
        );
        $access->method('mayAccess')->willReturn(true);

        $absences = $this->createMock(AbsenceRepository::class);
        $absences->expects(self::once())->method('findRequestedForUsers')->with([])->willReturn([]);
        $shifts = $this->createMock(WorkdayShiftRepository::class);
        $shifts->expects(self::once())->method('findRequestedForUsers')->with([$employee])->willReturn([
            WorkdayShiftBuilder::requested($employee, '2026-10-30', '2026-10-29'),
        ]);

        $counter = new OpenRequestCounter(new ApproverResolver($users, $access), $absences, $shifts);

        self::assertSame(1, $counter->countFor($boss));
    }
}
