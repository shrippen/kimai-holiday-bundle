<?php

namespace KimaiPlugin\HolidayBundle\Tests\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use App\WorkingTime\Mode\WorkingTimeModeDay;
use App\WorkingTime\Mode\WorkingTimeModeFactory;
use App\WorkingTime\Mode\WorkingTimeModeNone;
use KimaiPlugin\HolidayBundle\Entity\Location;
use KimaiPlugin\HolidayBundle\Entity\PublicHoliday;
use KimaiPlugin\HolidayBundle\Entity\PublicHolidayGroup;
use KimaiPlugin\HolidayBundle\Entity\WorkdayShift;
use KimaiPlugin\HolidayBundle\Enum\AbsenceStatus;
use KimaiPlugin\HolidayBundle\Repository\AbsenceRepository;
use KimaiPlugin\HolidayBundle\Repository\ExtraShiftRepository;
use KimaiPlugin\HolidayBundle\Repository\LocationExceptionRepository;
use KimaiPlugin\HolidayBundle\Repository\LocationUserRepository;
use KimaiPlugin\HolidayBundle\Repository\PublicHolidayGroupRepository;
use KimaiPlugin\HolidayBundle\Repository\PublicHolidayRepository;
use KimaiPlugin\HolidayBundle\Repository\WorkdayShiftRepository;
use KimaiPlugin\HolidayBundle\Service\AbsenceNotifier;
use KimaiPlugin\HolidayBundle\Service\AbsenceWorkdayHelper;
use KimaiPlugin\HolidayBundle\Service\ApproverAccess;
use KimaiPlugin\HolidayBundle\Service\ApproverResolver;
use KimaiPlugin\HolidayBundle\Service\LocationSchedule;
use KimaiPlugin\HolidayBundle\Service\PeriodLockGuard;
use KimaiPlugin\HolidayBundle\Service\UserWorkContract;
use KimaiPlugin\HolidayBundle\Service\WorkdayShiftOverrides;
use KimaiPlugin\HolidayBundle\Service\WorkdayShiftService;
use KimaiPlugin\HolidayBundle\Tests\Support\AbsenceBuilder;
use KimaiPlugin\HolidayBundle\Tests\Support\ExtraShiftBuilder;
use KimaiPlugin\HolidayBundle\Tests\Support\LocationBuilder;
use KimaiPlugin\HolidayBundle\Tests\Support\UserBuilder;
use KimaiPlugin\HolidayBundle\Tests\Support\WorkdayShiftBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class WorkdayShiftServiceTest extends TestCase
{
    /** @var list<array<string, mixed>> */
    private array $touching = [];
    private ?Location $location = null;
    private bool $hasLocation = true;
    /** @var list<\KimaiPlugin\HolidayBundle\Entity\Absence> */
    private array $absences = [];
    private ?\KimaiPlugin\HolidayBundle\Entity\ExtraShift $extra = null;
    private ?PeriodLockGuard $guard = null;
    /** @var MockObject&WorkdayShiftOverrides */
    private $overrides;
    /** @var MockObject&AbsenceNotifier */
    private $notifier;
    /** @var list<User> */
    private array $approvers = [];
    /** @var list<User> */
    private array $users = [];

    protected function setUp(): void
    {
        $this->touching = [];
        $this->hasLocation = true;
        $this->absences = [];
        $this->extra = null;
        $this->guard = null;
        $this->approvers = [];
        $this->users = [];
        $this->overrides = $this->createMock(WorkdayShiftOverrides::class);
        $this->notifier = $this->createMock(AbsenceNotifier::class);
    }

    public function testValidShiftByEmployeeIsRequested(): void
    {
        $moritz = $this->moritz();
        $shift = WorkdayShiftBuilder::requested($moritz, '2026-10-30', '2026-10-29')->setStatus(AbsenceStatus::NEW);
        $this->notifier->expects(self::once())->method('notifyShiftRequested')->with($shift);

        $this->service()->create($shift, $moritz);

        self::assertSame(AbsenceStatus::REQUESTED, $shift->getStatus());
        self::assertNull($shift->getApprovedBy());
    }

    public function testShiftByApproverIsApprovedDirectly(): void
    {
        $moritz = $this->moritz();
        $boss = UserBuilder::make()->build();
        $this->approvers = [$boss];
        $this->users = [$boss, $moritz];
        $shift = WorkdayShiftBuilder::requested($moritz, '2026-10-30', '2026-10-29')->setStatus(AbsenceStatus::NEW);
        $this->notifier->expects(self::once())->method('notifyShiftApproved')->with($shift);

        $this->service()->create($shift, $boss);

        self::assertSame(AbsenceStatus::APPROVED, $shift->getStatus());
        self::assertSame($boss, $shift->getApprovedBy());
        self::assertNotNull($shift->getApprovedAt());
    }

    public function testSameDay(): void
    {
        $this->assertInvalid('shift_same_day', $this->shift('2026-10-30', '2026-10-30'));
    }

    public function testNotSameWeek(): void
    {
        $this->assertInvalid('shift_not_same_week', $this->shift('2026-10-30', '2026-11-02'));
    }

    public function testFromNotWorkday(): void
    {
        // Do 29.10. hat keinen Vertragstag
        $this->assertInvalid('shift_from_not_workday', $this->shift('2026-10-29', '2026-10-28'));
    }

    public function testFromFullDayHoliday(): void
    {
        $user = UserBuilder::make()->fridaySaturday(8 * 3600)->startingOn('2026-01-01')->holidayGroup('1')->build();
        $this->assertInvalid('shift_from_not_workday', WorkdayShiftBuilder::requested($user, '2026-05-01', '2026-04-30'), $user);
    }

    public function testFromTaken(): void
    {
        $moritz = $this->moritz();
        $this->touching['2026-10-30'] = [WorkdayShiftBuilder::approved($moritz, '2026-10-30', '2026-10-31')];

        $this->assertInvalid('shift_from_taken', $this->shift('2026-10-30', '2026-10-29'), $moritz);
    }

    public function testToWorkday(): void
    {
        // Sa 31.10. ist Vertragstag
        $this->assertInvalid('shift_to_workday', $this->shift('2026-10-30', '2026-10-31'));
    }

    public function testToHoliday(): void
    {
        // Mi 28.10. ist kein Vertragstag, aber ganztägiger Feiertag
        $this->assertInvalid('shift_to_holiday', $this->shift('2026-10-30', '2026-10-28'), null, ['2026-10-28']);
    }

    public function testToOutsideContract(): void
    {
        $user = UserBuilder::make()->fridaySaturday(8 * 3600)->startingOn('2026-10-30')->build();
        $this->assertInvalid('shift_to_outside_contract', WorkdayShiftBuilder::requested($user, '2026-10-30', '2026-10-28'), $user);
    }

    public function testToTaken(): void
    {
        $moritz = $this->moritz();
        $this->touching['2026-10-29'] = [WorkdayShiftBuilder::approved($moritz, '2026-10-24', '2026-10-29')];

        $this->assertInvalid('shift_to_taken', $this->shift('2026-10-30', '2026-10-29'), $moritz);
    }

    public function testNoLocation(): void
    {
        $this->hasLocation = false;

        $this->assertInvalid('shift_no_location', $this->shift('2026-10-30', '2026-10-29'));
    }

    public function testToClosed(): void
    {
        // Mo 26.10.: Wien geschlossen
        $this->assertInvalid('shift_to_closed', $this->shift('2026-10-30', '2026-10-26'));
    }

    public function testAbsent(): void
    {
        $moritz = $this->moritz();
        $this->absences = [AbsenceBuilder::vacation($moritz, '2026-10-29', '2026-10-29')];

        $this->assertInvalid('shift_absent', $this->shift('2026-10-30', '2026-10-29'), $moritz);
    }

    public function testTargetExtra(): void
    {
        $moritz = $this->moritz();
        $this->extra = ExtraShiftBuilder::on($moritz, '2026-10-29');

        $this->assertInvalid('shift_target_extra', $this->shift('2026-10-30', '2026-10-29'), $moritz);
    }

    public function testLocked(): void
    {
        $moritz = $this->moritz();
        $guard = $this->createStub(PeriodLockGuard::class);
        $guard->method('assertOpen')->willThrowException(new \RuntimeException('holiday.error.month_locked'));
        $this->guard = $guard;

        $this->assertCreateInvalid('shift_locked', $this->shift('2026-10-30', '2026-10-29'), $moritz);
    }

    public function testLockOnEitherDayRejects(): void
    {
        $moritz = $this->moritz();
        $guard = $this->createStub(PeriodLockGuard::class);
        $guard->method('assertOpen')->willReturnCallback(static function (User $u, \DateTimeInterface $from): void {
            if ($from->format('Y-m-d') === '2026-10-29') {
                throw new \RuntimeException('holiday.error.month_locked');
            }
        });
        $this->guard = $guard;

        $this->assertCreateInvalid('shift_locked', $this->shift('2026-10-30', '2026-10-29'), $moritz);
    }

    public function testHasAbsenceBlocksDelete(): void
    {
        $moritz = $this->moritz();
        $shift = WorkdayShiftBuilder::approved($moritz, '2026-10-30', '2026-10-29');
        $this->absences = [AbsenceBuilder::vacation($moritz, '2026-10-29', '2026-10-29')];

        try {
            $this->service()->delete($shift);
            self::fail('delete must throw');
        } catch (\InvalidArgumentException $e) {
            self::assertSame('holiday.error.shift_has_absence', $e->getMessage());
        }
    }

    public function testDeleteApprovedBlockedByAbsenceOnTarget(): void
    {
        $moritz = $this->moritz();
        $shift = WorkdayShiftBuilder::approved($moritz, '2026-10-30', '2026-10-29');
        $this->absences = [AbsenceBuilder::vacation($moritz, '2026-10-28', '2026-10-30')];

        $this->expectExceptionMessage('holiday.error.shift_has_absence');
        $this->service()->delete($shift);
    }

    public function testDeleteRequestedIgnoresAbsence(): void
    {
        $moritz = $this->moritz();
        $shift = WorkdayShiftBuilder::requested($moritz, '2026-10-30', '2026-10-29');
        $this->absences = [AbsenceBuilder::vacation($moritz, '2026-10-29', '2026-10-29')];
        $this->overrides->expects(self::once())->method('reset')->with($moritz);

        $this->service()->delete($shift);
        $this->addToAssertionCount(1);
    }

    public function testApproveRevalidatesExtraShiftOnTarget(): void
    {
        $moritz = $this->moritz();
        $boss = UserBuilder::make()->build();
        $shift = WorkdayShiftBuilder::requested($moritz, '2026-10-30', '2026-10-29');
        $this->extra = ExtraShiftBuilder::on($moritz, '2026-10-29');

        try {
            $this->service()->approve($shift, $boss);
            self::fail('approve must throw');
        } catch (\InvalidArgumentException $e) {
            self::assertSame('holiday.error.shift_target_extra', $e->getMessage());
        }
        self::assertSame(AbsenceStatus::REQUESTED, $shift->getStatus());
    }

    public function testApproverMayNotApproveOwnShift(): void
    {
        $boss = $this->moritz();
        $shift = WorkdayShiftBuilder::requested($boss, '2026-10-30', '2026-10-29');

        try {
            $this->service()->approve($shift, $boss);
            self::fail('self approval must throw');
        } catch (\InvalidArgumentException $e) {
            self::assertSame('holiday.error.self_approval', $e->getMessage());
        }
        self::assertSame(AbsenceStatus::REQUESTED, $shift->getStatus());
    }

    public function testApproveOnlyRequested(): void
    {
        $moritz = $this->moritz();
        $boss = UserBuilder::make()->build();

        $this->expectExceptionMessage('holiday.error.not_requested');
        $this->service()->approve(WorkdayShiftBuilder::approved($moritz, '2026-10-30', '2026-10-29'), $boss);
    }

    public function testStatusChangeResetsOverrides(): void
    {
        $moritz = $this->moritz();
        $boss = UserBuilder::make()->build();
        $shift = WorkdayShiftBuilder::requested($moritz, '2026-10-30', '2026-10-29');
        $this->overrides->expects(self::exactly(3))->method('reset')->with($moritz);
        $this->notifier->expects(self::once())->method('notifyShiftApproved');
        $this->notifier->expects(self::once())->method('notifyShiftRequested');
        $service = $this->service();

        $service->approve($shift, $boss);
        self::assertSame(AbsenceStatus::APPROVED, $shift->getStatus());
        self::assertSame($boss, $shift->getApprovedBy());

        $service->request($shift, true);
        self::assertSame(AbsenceStatus::REQUESTED, $shift->getStatus());
        self::assertNull($shift->getApprovedBy());

        $service->delete($shift);
    }

    public function testRejectSetsStatusAndNotifies(): void
    {
        $moritz = $this->moritz();
        $boss = UserBuilder::make()->build();
        $shift = WorkdayShiftBuilder::requested($moritz, '2026-10-30', '2026-10-29');
        $this->notifier->expects(self::once())->method('notifyShiftRejected')->with($shift);

        $this->service()->reject($shift, $boss);

        self::assertSame(AbsenceStatus::REJECTED, $shift->getStatus());
        self::assertSame($boss, $shift->getApprovedBy());
    }

    private function moritz(): User
    {
        return UserBuilder::make()->fridaySaturday(8 * 3600)->startingOn('2026-09-25')->holidayGroup('1')->build();
    }

    private function shift(string $from, string $to): WorkdayShift
    {
        return WorkdayShiftBuilder::requested($this->moritz(), $from, $to);
    }

    /**
     * @param list<string> $extraHolidays additional full-day holidays
     */
    private function assertInvalid(string $key, WorkdayShift $shift, ?User $user = null, array $extraHolidays = []): void
    {
        try {
            $this->service($extraHolidays)->validate($shift);
            self::fail('validate must throw ' . $key);
        } catch (\InvalidArgumentException $e) {
            self::assertSame('holiday.error.' . $key, $e->getMessage());
        }
    }

    private function assertCreateInvalid(string $key, WorkdayShift $shift, User $actor): void
    {
        try {
            $this->service()->create($shift, $actor);
            self::fail('create must throw ' . $key);
        } catch (\InvalidArgumentException $e) {
            self::assertSame('holiday.error.' . $key, $e->getMessage());
        }
    }

    /**
     * @param list<string> $extraHolidays
     */
    private function service(array $extraHolidays = []): WorkdayShiftService
    {
        $group = new PublicHolidayGroup();
        (new \ReflectionProperty(PublicHolidayGroup::class, 'id'))->setValue($group, 1);
        $groups = $this->createStub(PublicHolidayGroupRepository::class);
        $groups->method('find')->willReturn($group);

        $holidays = [];
        foreach (['2026-05-01', ...$extraHolidays] as $ymd) {
            $holidays[] = (new PublicHoliday())->setDate(new \DateTimeImmutable($ymd))->setHalfDay(false);
        }
        $holidayRepo = $this->createStub(PublicHolidayRepository::class);
        $holidayRepo->method('findByGroupAndYear')->willReturn($holidays);
        $holidayRepo->method('findByGroupBetween')->willReturn([]);

        $factory = new WorkingTimeModeFactory([new WorkingTimeModeNone(), new WorkingTimeModeDay()], new NullLogger());
        $contract = new UserWorkContract($factory, $groups);
        $helper = new AbsenceWorkdayHelper($contract, $holidayRepo);

        $exceptions = $this->createStub(LocationExceptionRepository::class);
        $exceptions->method('findBetween')->willReturn([]);
        $schedule = new LocationSchedule($exceptions, $holidayRepo);

        $locations = $this->createStub(LocationUserRepository::class);
        $locations->method('findLocationOf')->willReturn($this->hasLocation ? LocationBuilder::vienna($group) : null);

        $touching = $this->touching;
        $shifts = $this->createStub(WorkdayShiftRepository::class);
        $shifts->method('findActiveTouching')->willReturnCallback(
            static fn (User $u, \DateTimeInterface $day): array => $touching[$day->format('Y-m-d')] ?? []
        );

        $absences = $this->createStub(AbsenceRepository::class);
        $absences->method('findApprovedBetween')->willReturn($this->absences);

        $extras = $this->createStub(ExtraShiftRepository::class);
        $extras->method('findOneByUserAndDate')->willReturnCallback(fn (): ?\KimaiPlugin\HolidayBundle\Entity\ExtraShift => $this->extra);

        $users = $this->createStub(UserRepository::class);
        $users->method('findBy')->willReturn($this->users);
        $approvers = $this->approvers;
        $access = $this->createStub(ApproverAccess::class);
        $access->method('mayApprove')->willReturnCallback(
            static fn (User $u, string $permission = ''): bool => $permission === 'approve_shift' && \in_array($u, $approvers, true)
        );
        $access->method('mayAccess')->willReturn(true);

        return new WorkdayShiftService(
            $shifts,
            $contract,
            $helper,
            $locations,
            $schedule,
            $absences,
            $extras,
            $this->guard ?? $this->createStub(PeriodLockGuard::class),
            new ApproverResolver($users, $access),
            $this->notifier,
            $this->overrides,
        );
    }
}
