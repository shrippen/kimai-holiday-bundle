<?php

namespace KimaiPlugin\HolidayBundle\Tests\Service;

use App\Entity\WorkingTime;
use App\Event\WorkingTimeYearEvent;
use App\WorkingTime\Mode\WorkingTimeModeDay;
use App\WorkingTime\Mode\WorkingTimeModeFactory;
use App\WorkingTime\Mode\WorkingTimeModeNone;
use App\WorkingTime\Model\Year;
use KimaiPlugin\HolidayBundle\EventSubscriber\WorkingTimeYearSubscriber;
use KimaiPlugin\HolidayBundle\Repository\AbsenceRepository;
use KimaiPlugin\HolidayBundle\Repository\PublicHolidayGroupRepository;
use KimaiPlugin\HolidayBundle\Repository\PublicHolidayRepository;
use KimaiPlugin\HolidayBundle\Repository\WorkdayShiftRepository;
use KimaiPlugin\HolidayBundle\Service\AbsenceTimesheetService;
use KimaiPlugin\HolidayBundle\Service\AbsenceWorkdayHelper;
use KimaiPlugin\HolidayBundle\Service\DayExpectation;
use KimaiPlugin\HolidayBundle\Service\HolidayConfiguration;
use KimaiPlugin\HolidayBundle\Service\UserWorkContract;
use KimaiPlugin\HolidayBundle\Service\WorkdayShiftOverrides;
use KimaiPlugin\HolidayBundle\Tests\Support\UserBuilder;
use KimaiPlugin\HolidayBundle\Tests\Support\WorkdayShiftBuilder;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Contracts\Translation\TranslatorInterface;

final class WorkingTimeYearSubscriberTest extends TestCase
{
    private const FRI = '2026-10-30';
    private const THU = '2026-10-29';

    public function testShiftOverridesPastDays(): void
    {
        $user = UserBuilder::make()->fridaySaturday(8 * 3600)->build();
        $year = new Year(new \DateTimeImmutable('2026-01-01'), $user);
        // Kimai's own calculation for past days: contract hours of the weekday.
        $this->setExpected($year, self::FRI, 8 * 3600);
        $this->setExpected($year, self::THU, 0);
        $this->setExpected($year, '2026-10-31', 8 * 3600);

        $this->subscriber($user)->onWorkingTimeYear(new WorkingTimeYearEvent($year, new \DateTimeImmutable('2026-11-15')));

        $fri = $year->getDay(new \DateTimeImmutable(self::FRI));
        self::assertSame(0, $fri->getWorkingTime()->getExpectedTime());
        self::assertSame(['holiday.shift.addon.away'], array_map(static fn ($a) => $a->getTitle(), $fri->getAddons()));

        $thu = $year->getDay(new \DateTimeImmutable(self::THU));
        self::assertSame(8 * 3600, $thu->getWorkingTime()->getExpectedTime());
        self::assertSame(['holiday.shift.addon.to'], array_map(static fn ($a) => $a->getTitle(), $thu->getAddons()));

        $sat = $year->getDay(new \DateTimeImmutable('2026-10-31'));
        self::assertSame(8 * 3600, $sat->getWorkingTime()->getExpectedTime());
        self::assertSame([], $sat->getAddons());
    }

    public function testLockedDayIsUntouched(): void
    {
        $user = UserBuilder::make()->fridaySaturday(8 * 3600)->build();
        $year = new Year(new \DateTimeImmutable('2026-01-01'), $user);
        $this->setExpected($year, self::FRI, 8 * 3600);
        $year->getDay(new \DateTimeImmutable(self::FRI))->getWorkingTime()->setApprovedAt(new \DateTimeImmutable('2026-11-01'));
        $year->getDay(new \DateTimeImmutable(self::FRI))->getWorkingTime()->setApprovedBy($user);
        $this->setExpected($year, self::THU, 0);

        $this->subscriber($user)->onWorkingTimeYear(new WorkingTimeYearEvent($year, new \DateTimeImmutable('2026-11-15')));

        $fri = $year->getDay(new \DateTimeImmutable(self::FRI));
        self::assertSame(8 * 3600, $fri->getWorkingTime()->getExpectedTime());
        self::assertSame([], $fri->getAddons());
    }

    private function setExpected(Year $year, string $ymd, int $seconds): void
    {
        $date = new \DateTimeImmutable($ymd);
        $day = $year->getDay($date);
        $workingTime = new WorkingTime($year->getUser(), $date);
        $workingTime->setExpectedTime($seconds);
        $day->setWorkingTime($workingTime);
    }

    private function subscriber($user): WorkingTimeYearSubscriber
    {
        $shifts = $this->createStub(WorkdayShiftRepository::class);
        $shifts->method('findApprovedForUserBetween')->willReturn([WorkdayShiftBuilder::approved($user, self::FRI, self::THU)]);
        $overrides = new WorkdayShiftOverrides($shifts);
        $factory = new WorkingTimeModeFactory([new WorkingTimeModeNone(), new WorkingTimeModeDay()], new NullLogger());
        $contract = new UserWorkContract($factory, $this->createStub(PublicHolidayGroupRepository::class), $overrides);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id) => $id);

        return new WorkingTimeYearSubscriber(
            $this->createStub(AbsenceRepository::class),
            $this->createStub(PublicHolidayRepository::class),
            $contract,
            $this->createStub(HolidayConfiguration::class),
            $translator,
            $this->createStub(AbsenceWorkdayHelper::class),
            $this->createStub(AbsenceTimesheetService::class),
            new DayExpectation(),
            $overrides,
        );
    }
}
