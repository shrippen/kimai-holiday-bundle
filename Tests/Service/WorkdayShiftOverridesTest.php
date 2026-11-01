<?php

namespace KimaiPlugin\HolidayBundle\Tests\Service;

use KimaiPlugin\HolidayBundle\Repository\WorkdayShiftRepository;
use KimaiPlugin\HolidayBundle\Service\WorkdayShiftOverrides;
use KimaiPlugin\HolidayBundle\Tests\Support\UserBuilder;
use KimaiPlugin\HolidayBundle\Tests\Support\WorkdayShiftBuilder;
use PHPUnit\Framework\TestCase;

final class WorkdayShiftOverridesTest extends TestCase
{
    public function testSourceForReturnsOriginDay(): void
    {
        $user = UserBuilder::make()->fridaySaturday(8 * 3600)->build();
        $overrides = $this->overrides([WorkdayShiftBuilder::approved($user, '2026-10-30', '2026-10-29')]);

        self::assertSame('2026-10-30', $overrides->sourceFor($user, new \DateTimeImmutable('2026-10-29'))?->format('Y-m-d'));
        self::assertNull($overrides->sourceFor($user, new \DateTimeImmutable('2026-10-30')));
        self::assertTrue($overrides->isShiftedAway($user, new \DateTimeImmutable('2026-10-30')));
        self::assertFalse($overrides->isShiftedAway($user, new \DateTimeImmutable('2026-10-29')));
    }

    public function testRejectedShiftHasNoEffect(): void
    {
        $user = UserBuilder::make()->fridaySaturday(8 * 3600)->build();
        $overrides = $this->overrides([WorkdayShiftBuilder::rejected($user, '2026-10-30', '2026-10-29')]);

        self::assertNull($overrides->sourceFor($user, new \DateTimeImmutable('2026-10-29')));
        self::assertFalse($overrides->isShiftedAway($user, new \DateTimeImmutable('2026-10-30')));
    }

    public function testOneQueryPerUserAndYear(): void
    {
        $user = UserBuilder::make()->build();
        $repo = $this->createMock(WorkdayShiftRepository::class);
        $repo->expects(self::once())->method('findApprovedForUserBetween')->willReturn([]);
        $overrides = new WorkdayShiftOverrides($repo);

        for ($day = new \DateTimeImmutable('2026-01-01'); $day->format('Y') === '2026'; $day = $day->modify('+1 day')) {
            $overrides->sourceFor($user, $day);
        }
    }

    public function testSecondUserQueriesAgain(): void
    {
        $repo = $this->createMock(WorkdayShiftRepository::class);
        $repo->expects(self::exactly(2))->method('findApprovedForUserBetween')->willReturn([]);
        $overrides = new WorkdayShiftOverrides($repo);
        $day = new \DateTimeImmutable('2026-10-29');

        $overrides->sourceFor(UserBuilder::make()->build(), $day);
        $overrides->sourceFor(UserBuilder::make()->build(), $day);
    }

    public function testResetClearsCache(): void
    {
        $user = UserBuilder::make()->build();
        $repo = $this->createMock(WorkdayShiftRepository::class);
        $repo->expects(self::exactly(2))->method('findApprovedForUserBetween')->willReturn([]);
        $overrides = new WorkdayShiftOverrides($repo);
        $day = new \DateTimeImmutable('2026-10-29');

        $overrides->sourceFor($user, $day);
        $overrides->reset($user);
        $overrides->sourceFor($user, $day);
    }

    /** @param \KimaiPlugin\HolidayBundle\Entity\WorkdayShift[] $shifts */
    private function overrides(array $shifts): WorkdayShiftOverrides
    {
        $repo = $this->createStub(WorkdayShiftRepository::class);
        $repo->method('findApprovedForUserBetween')->willReturn($shifts);

        return new WorkdayShiftOverrides($repo);
    }
}
