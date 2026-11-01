<?php

namespace KimaiPlugin\HolidayBundle\Tests\Service;

use App\WorkingTime\Mode\WorkingTimeModeDay;
use App\WorkingTime\Mode\WorkingTimeModeFactory;
use App\WorkingTime\Mode\WorkingTimeModeNone;
use KimaiPlugin\HolidayBundle\Repository\PublicHolidayGroupRepository;
use KimaiPlugin\HolidayBundle\Repository\WorkdayShiftRepository;
use KimaiPlugin\HolidayBundle\Service\UserWorkContract;
use KimaiPlugin\HolidayBundle\Service\WorkdayShiftOverrides;
use KimaiPlugin\HolidayBundle\Tests\Support\UserBuilder;
use KimaiPlugin\HolidayBundle\Tests\Support\WorkdayShiftBuilder;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class UserWorkContractTest extends TestCase
{
    public function testSaturdayWorkerHasExpectedSecondsOnSaturday(): void
    {
        $user = UserBuilder::make()->wednesdayToSaturday(7 * 3600, 9 * 3600)->build();
        $contract = $this->contract();

        self::assertSame(9 * 3600, $contract->getExpectedSecondsForDate($user, new \DateTimeImmutable('2026-10-24'))); // Sa
        self::assertSame(0, $contract->getExpectedSecondsForDate($user, new \DateTimeImmutable('2026-10-26'))); // Mo
    }

    public function testBeforeStartIsZero(): void
    {
        $user = UserBuilder::make()->wednesdayToSaturday(7 * 3600, 9 * 3600)->startingOn('2026-11-01')->build();

        self::assertSame(0, $this->contract()->getExpectedSecondsForDate($user, new \DateTimeImmutable('2026-10-24')));
    }

    public function testShiftMovesExpectedSeconds(): void
    {
        $user = UserBuilder::make()->fridaySaturday(8 * 3600)->build();
        $contract = $this->shiftedContract($user);

        self::assertSame(8 * 3600, $contract->getExpectedSecondsForDate($user, new \DateTimeImmutable('2026-10-29'))); // Do
        self::assertSame(0, $contract->getExpectedSecondsForDate($user, new \DateTimeImmutable('2026-10-30'))); // Fr
        self::assertSame(8 * 3600, $contract->getExpectedSecondsForDate($user, new \DateTimeImmutable('2026-10-31'))); // Sa
    }

    public function testContractSecondsIgnoreShift(): void
    {
        $user = UserBuilder::make()->fridaySaturday(8 * 3600)->build();
        $contract = $this->shiftedContract($user);

        self::assertSame(0, $contract->getContractSecondsForDate($user, new \DateTimeImmutable('2026-10-29')));
        self::assertSame(8 * 3600, $contract->getContractSecondsForDate($user, new \DateTimeImmutable('2026-10-30')));
    }

    public function testTargetOutsideContractPeriodIsZero(): void
    {
        $user = UserBuilder::make()->fridaySaturday(8 * 3600)->startingOn('2026-10-30')->build();

        self::assertSame(0, $this->shiftedContract($user)->getExpectedSecondsForDate($user, new \DateTimeImmutable('2026-10-29')));
    }

    private function shiftedContract(\App\Entity\User $user): UserWorkContract
    {
        $repo = $this->createStub(WorkdayShiftRepository::class);
        $repo->method('findApprovedForUserBetween')->willReturn([WorkdayShiftBuilder::approved($user, '2026-10-30', '2026-10-29')]);
        $factory = new WorkingTimeModeFactory([new WorkingTimeModeNone(), new WorkingTimeModeDay()], new NullLogger());

        return new UserWorkContract($factory, $this->createStub(PublicHolidayGroupRepository::class), new WorkdayShiftOverrides($repo));
    }

    private function contract(): UserWorkContract
    {
        $factory = new WorkingTimeModeFactory([new WorkingTimeModeNone(), new WorkingTimeModeDay()], new NullLogger());

        return new UserWorkContract($factory, $this->createStub(PublicHolidayGroupRepository::class));
    }
}
