<?php

namespace KimaiPlugin\HolidayBundle\Tests\Service;

use App\Repository\WorkingTimeRepository;
use KimaiPlugin\HolidayBundle\Repository\MonthLockRepository;
use KimaiPlugin\HolidayBundle\Service\PeriodLockGuard;
use KimaiPlugin\HolidayBundle\Tests\Support\UserBuilder;
use PHPUnit\Framework\TestCase;

final class PeriodLockGuardTest extends TestCase
{
    public function testKimaiApprovalDateBlocks(): void
    {
        $guard = $this->guard(new \DateTimeImmutable('2026-10-31'), false);

        $this->expectExceptionMessage('holiday.error.month_locked');
        $guard->assertOpen(UserBuilder::make()->build(), new \DateTimeImmutable('2026-10-15'), new \DateTimeImmutable('2026-11-02'));
    }

    public function testMonthLockBlocks(): void
    {
        $guard = $this->guard(null, true);

        $this->expectExceptionMessage('holiday.error.month_locked');
        $guard->assertOpen(UserBuilder::make()->build(), new \DateTimeImmutable('2026-10-15'), new \DateTimeImmutable('2026-10-16'));
    }

    public function testOpenRangePasses(): void
    {
        $this->guard(new \DateTimeImmutable('2026-09-30'), false)
            ->assertOpen(UserBuilder::make()->build(), new \DateTimeImmutable('2026-10-01'), new \DateTimeImmutable('2026-11-05'));
        $this->addToAssertionCount(1);
    }

    private function guard(?\DateTimeImmutable $approvedUntil, bool $monthLocked): PeriodLockGuard
    {
        $workingTime = $this->createStub(WorkingTimeRepository::class);
        $workingTime->method('getLatestApprovalDate')->willReturn($approvedUntil);
        $locks = $this->createStub(MonthLockRepository::class);
        $locks->method('isDateLocked')->willReturn($monthLocked);

        return new PeriodLockGuard($workingTime, $locks);
    }
}
