<?php

namespace KimaiPlugin\HolidayBundle\Tests\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use KimaiPlugin\HolidayBundle\Entity\Absence;
use KimaiPlugin\HolidayBundle\Enum\AbsenceStatus;
use KimaiPlugin\HolidayBundle\Enum\AbsenceType;
use KimaiPlugin\HolidayBundle\Repository\AbsenceRepository;
use KimaiPlugin\HolidayBundle\Service\PeriodLockGuard;
use KimaiPlugin\HolidayBundle\Service\AbsenceApprovalService;
use KimaiPlugin\HolidayBundle\Service\AbsenceNotifier;
use KimaiPlugin\HolidayBundle\Service\AbsenceTimesheetService;
use KimaiPlugin\HolidayBundle\Service\ApproverAccess;
use KimaiPlugin\HolidayBundle\Service\ApproverResolver;
use KimaiPlugin\HolidayBundle\Service\HolidayConfiguration;
use KimaiPlugin\HolidayBundle\Tests\Support\AbsenceBuilder;
use KimaiPlugin\HolidayBundle\Tests\Support\UserBuilder;
use PHPUnit\Framework\TestCase;

final class AbsenceApprovalServiceTest extends TestCase
{
    public function testHalfDayVacationRejectedWhenNotAllowed(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('holiday.error.half_day_not_allowed');

        $this->service(false)->validate($this->absence(AbsenceType::VACATION, true));
    }

    public function testHalfDayVacationAcceptedWhenAllowed(): void
    {
        $this->service(true)->validate($this->absence(AbsenceType::VACATION, true));
        $this->addToAssertionCount(1);
    }

    public function testFullDayVacationAcceptedWhenHalfDaysNotAllowed(): void
    {
        $this->service(false)->validate($this->absence(AbsenceType::VACATION, false));
        $this->addToAssertionCount(1);
    }

    public function testHalfDaySicknessAcceptedRegardless(): void
    {
        $this->service(false)->validate($this->absence(AbsenceType::SICKNESS, true));
        $this->addToAssertionCount(1);
    }

    public function testApproverMayNotApproveOwnAbsence(): void
    {
        $boss = UserBuilder::make()->build();
        $absence = AbsenceBuilder::requested($boss, '2026-10-21', '2026-10-21');

        try {
            $this->service(true)->approve($absence, $boss);
            $this->fail('self approval must throw');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('holiday.error.self_approval', $e->getMessage());
        }
        $this->assertSame(AbsenceStatus::REQUESTED, $absence->getStatus());
    }

    public function testApproverMayNotRejectOwnAbsence(): void
    {
        $boss = UserBuilder::make()->build();
        $absence = AbsenceBuilder::requested($boss, '2026-10-21', '2026-10-21');

        $this->expectExceptionMessage('holiday.error.self_approval');
        $this->service(true)->reject($absence, $boss);
    }

    public function testApproverCreatingForOtherIsApprovedDirectly(): void
    {
        $boss = UserBuilder::make()->build();
        $employee = UserBuilder::make()->build();
        $absence = AbsenceBuilder::vacation($employee, '2026-10-21', '2026-10-21')->setStatus(AbsenceStatus::NEW);

        $this->service(true, [$boss], [$boss, $employee])->create($absence, $boss);

        $this->assertSame(AbsenceStatus::APPROVED, $absence->getStatus());
        $this->assertSame($boss, $absence->getApprovedBy());
    }

    public function testUserCreatingForSelfIsRequested(): void
    {
        $boss = UserBuilder::make()->build();
        $absence = AbsenceBuilder::vacation($boss, '2026-10-21', '2026-10-21')->setStatus(AbsenceStatus::NEW);

        $this->service(true, [$boss], [$boss])->create($absence, $boss);

        $this->assertSame(AbsenceStatus::REQUESTED, $absence->getStatus());
        $this->assertNull($absence->getApprovedBy());
    }

    public function testApproverEditingOthersAbsenceKeepsItApproved(): void
    {
        $boss = UserBuilder::make()->build();
        $employee = UserBuilder::make()->build();
        $absence = AbsenceBuilder::vacation($employee, '2026-10-21', '2026-10-21')->setStatus(AbsenceStatus::APPROVED);

        $this->service(true, [$boss], [$boss, $employee])->update($absence, $boss);

        $this->assertSame(AbsenceStatus::APPROVED, $absence->getStatus());
        $this->assertSame($boss, $absence->getApprovedBy());
    }

    public function testUserEditingOwnAbsenceGoesBackToRequested(): void
    {
        $boss = UserBuilder::make()->build();
        $absence = AbsenceBuilder::vacation($boss, '2026-10-21', '2026-10-21')->setStatus(AbsenceStatus::APPROVED);

        $this->service(true, [$boss], [$boss])->update($absence, $boss);

        $this->assertSame(AbsenceStatus::REQUESTED, $absence->getStatus());
        $this->assertNull($absence->getApprovedBy());
    }

    public function testCreateChecksLockForAbsenceRange(): void
    {
        $user = UserBuilder::make()->build();
        $absence = AbsenceBuilder::vacation($user, '2026-10-21', '2026-10-23')->setStatus(AbsenceStatus::NEW);
        $guard = $this->createMock(PeriodLockGuard::class);
        $guard->expects($this->once())->method('assertOpen')->with($user, $absence->getStartDate(), $absence->getEndDate());

        $this->service(true, [], [], $guard)->create($absence, $user);
    }

    public function testUpdateChecksPreviousAndNewRange(): void
    {
        $user = UserBuilder::make()->build();
        $absence = AbsenceBuilder::vacation($user, '2026-10-21', '2026-10-23');
        $previousStart = new \DateTimeImmutable('2026-09-01');
        $previousEnd = new \DateTimeImmutable('2026-09-02');
        $calls = [];
        $guard = $this->createMock(PeriodLockGuard::class);
        $guard->expects($this->exactly(2))->method('assertOpen')->willReturnCallback(
            static function (User $u, \DateTimeInterface $from, \DateTimeInterface $to) use (&$calls): void {
                $calls[] = [$from->format('Y-m-d'), $to->format('Y-m-d')];
            }
        );

        $this->service(true, [], [], $guard)->update($absence, $user, $previousStart, $previousEnd);

        $this->assertSame([['2026-09-01', '2026-09-02'], ['2026-10-21', '2026-10-23']], $calls);
    }

    public function testRequestApproveRejectAndDeleteCheckLock(): void
    {
        $employee = UserBuilder::make()->build();
        $boss = UserBuilder::make()->build();
        $absence = AbsenceBuilder::requested($employee, '2026-10-21', '2026-10-22');
        $guard = $this->createMock(PeriodLockGuard::class);
        $guard->expects($this->exactly(4))->method('assertOpen')
            ->with($employee, $absence->getStartDate(), $absence->getEndDate());
        $service = $this->service(true, [], [], $guard);

        $service->approve($absence, $boss);
        $service->request($absence, false);
        $service->reject($absence, $boss);
        $service->delete($absence);
    }

    public function testApproveStopsWhenPeriodIsLocked(): void
    {
        $employee = UserBuilder::make()->build();
        $boss = UserBuilder::make()->build();
        $absence = AbsenceBuilder::requested($employee, '2026-10-21', '2026-10-22');
        $guard = $this->createStub(PeriodLockGuard::class);
        $guard->method('assertOpen')->willThrowException(new \RuntimeException('holiday.error.month_locked'));

        try {
            $this->service(true, [], [], $guard)->approve($absence, $boss);
            $this->fail('locked period must throw');
        } catch (\RuntimeException $e) {
            $this->assertSame('holiday.error.month_locked', $e->getMessage());
        }
        $this->assertSame(AbsenceStatus::REQUESTED, $absence->getStatus());
        $this->assertNull($absence->getApprovedBy());
    }

    private function absence(AbsenceType $type, bool $half): Absence
    {
        $user = UserBuilder::make()->build();

        return AbsenceBuilder::vacation($user, '2026-10-21', '2026-10-21')->setType($type)->setHalfDay($half);
    }

    /**
     * Runs against the real ApproverResolver; only the permission seam and the user list are stubbed.
     *
     * @param list<User> $approvers users holding the approval permission (they access everyone)
     * @param list<User> $users     all enabled users
     */
    private function service(bool $allowHalfDay, array $approvers = [], array $users = [], ?PeriodLockGuard $guard = null): AbsenceApprovalService
    {
        $repository = $this->createStub(UserRepository::class);
        $repository->method('findBy')->willReturn($users);
        $access = $this->createStub(ApproverAccess::class);
        $access->method('mayApprove')->willReturnCallback(static fn (User $u): bool => \in_array($u, $approvers, true));
        $access->method('mayAccess')->willReturn(true);
        $resolver = new ApproverResolver($repository, $access);

        $config = $this->createStub(HolidayConfiguration::class);
        $config->method('allowHalfDayVacation')->willReturn($allowHalfDay);

        return new AbsenceApprovalService(
            $this->createStub(AbsenceRepository::class),
            $this->createStub(AbsenceNotifier::class),
            $config,
            $this->createStub(AbsenceTimesheetService::class),
            $guard ?? $this->createStub(PeriodLockGuard::class),
            $resolver,
        );
    }
}
