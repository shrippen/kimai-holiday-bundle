<?php

namespace KimaiPlugin\HolidayBundle\Tests\Service;

use App\WorkingTime\Mode\WorkingTimeModeDay;
use App\WorkingTime\Mode\WorkingTimeModeFactory;
use App\WorkingTime\Mode\WorkingTimeModeNone;
use KimaiPlugin\HolidayBundle\Entity\PublicHoliday;
use KimaiPlugin\HolidayBundle\Entity\PublicHolidayGroup;
use KimaiPlugin\HolidayBundle\Repository\PublicHolidayGroupRepository;
use KimaiPlugin\HolidayBundle\Repository\PublicHolidayRepository;
use KimaiPlugin\HolidayBundle\Repository\WorkdayShiftRepository;
use KimaiPlugin\HolidayBundle\Service\AbsenceWorkdayHelper;
use KimaiPlugin\HolidayBundle\Service\UserWorkContract;
use KimaiPlugin\HolidayBundle\Service\WorkdayShiftOverrides;
use KimaiPlugin\HolidayBundle\Tests\Support\AbsenceBuilder;
use KimaiPlugin\HolidayBundle\Tests\Support\UserBuilder;
use KimaiPlugin\HolidayBundle\Tests\Support\WorkdayShiftBuilder;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class AbsenceWorkdayHelperTest extends TestCase
{
    public function testSaturdayCountsForSaturdayWorker(): void
    {
        $user = UserBuilder::make()->wednesdayToSaturday(7 * 3600, 9 * 3600)->build();
        $absence = AbsenceBuilder::vacation($user, '2026-10-21', '2026-10-24'); // Mi–Sa

        self::assertSame(4.0, $this->helper()->countDaysBetween($absence, null, null));
    }

    public function testMondayDoesNotCount(): void
    {
        $user = UserBuilder::make()->wednesdayToSaturday(7 * 3600, 9 * 3600)->build();

        self::assertFalse($this->helper()->isAbsenceApplicableDay($user, new \DateTimeImmutable('2026-10-26')));
    }

    public function testFullDayHolidayOnSaturdayIsNotApplicable(): void
    {
        // 26.12.2026 ist ein Samstag; Gruppe 1 hat dort einen ganztägigen Feiertag
        $user = UserBuilder::make()->wednesdayToSaturday(7 * 3600, 9 * 3600)->holidayGroup('1')->build();
        $date = new \DateTimeImmutable('2026-12-26');
        $helper = $this->helperWithHoliday('2026-12-26');

        self::assertFalse($helper->isAbsenceApplicableDay($user, $date));
        self::assertTrue($helper->isNonWorkingDay($user, $date));
    }

    public function testCountDaysBetweenClipsToWindow(): void
    {
        $user = UserBuilder::make()->wednesdayToSaturday(7 * 3600, 9 * 3600)->build();
        $absence = AbsenceBuilder::vacation($user, '2026-10-21', '2026-10-24');

        self::assertSame(
            2.0,
            $this->helper()->countDaysBetween($absence, new \DateTimeImmutable('2026-10-23'), new \DateTimeImmutable('2026-10-31'))
        );
    }

    public function testShiftedTargetIsApplicableDay(): void
    {
        $user = UserBuilder::make()->fridaySaturday(8 * 3600)->build();
        $repo = $this->createStub(WorkdayShiftRepository::class);
        $repo->method('findApprovedForUserBetween')->willReturn([WorkdayShiftBuilder::approved($user, '2026-10-30', '2026-10-29')]);
        $factory = new WorkingTimeModeFactory([new WorkingTimeModeNone(), new WorkingTimeModeDay()], new NullLogger());
        $contract = new UserWorkContract($factory, $this->createStub(PublicHolidayGroupRepository::class), new WorkdayShiftOverrides($repo));
        $helper = new AbsenceWorkdayHelper($contract, $this->createStub(PublicHolidayRepository::class));

        self::assertSame(1.0, $helper->countDaysBetween(AbsenceBuilder::vacation($user, '2026-10-29', '2026-10-29'), null, null));
        self::assertSame(0.0, $helper->countDaysBetween(AbsenceBuilder::vacation($user, '2026-10-30', '2026-10-30'), null, null));
    }

    private function helper(): AbsenceWorkdayHelper
    {
        return new AbsenceWorkdayHelper($this->contract($this->createStub(PublicHolidayGroupRepository::class)), $this->createStub(PublicHolidayRepository::class));
    }

    private function helperWithHoliday(string $ymd): AbsenceWorkdayHelper
    {
        $group = new PublicHolidayGroup();
        $id = new \ReflectionProperty(PublicHolidayGroup::class, 'id');
        $id->setValue($group, 1);

        $groups = $this->createStub(PublicHolidayGroupRepository::class);
        $groups->method('find')->willReturn($group);

        $holiday = (new PublicHoliday())->setDate(new \DateTimeImmutable($ymd))->setHalfDay(false);
        $holidays = $this->createStub(PublicHolidayRepository::class);
        $holidays->method('findByGroupAndYear')->willReturn([$holiday]);

        return new AbsenceWorkdayHelper($this->contract($groups), $holidays);
    }

    private function contract(PublicHolidayGroupRepository $groups): UserWorkContract
    {
        $factory = new WorkingTimeModeFactory([new WorkingTimeModeNone(), new WorkingTimeModeDay()], new NullLogger());

        return new UserWorkContract($factory, $groups);
    }
}
