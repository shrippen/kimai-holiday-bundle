<?php

namespace KimaiPlugin\HolidayBundle\Tests\Service;

use App\Entity\User;
use App\WorkingTime\Mode\WorkingTimeModeDay;
use App\WorkingTime\Mode\WorkingTimeModeFactory;
use App\WorkingTime\Mode\WorkingTimeModeNone;
use KimaiPlugin\HolidayBundle\Entity\Absence;
use KimaiPlugin\HolidayBundle\Entity\ExtraShift;
use KimaiPlugin\HolidayBundle\Entity\Location;
use KimaiPlugin\HolidayBundle\Entity\PublicHoliday;
use KimaiPlugin\HolidayBundle\Entity\PublicHolidayGroup;
use KimaiPlugin\HolidayBundle\Entity\WorkdayShift;
use KimaiPlugin\HolidayBundle\Enum\AbsenceStatus;
use KimaiPlugin\HolidayBundle\Enum\PresenceKind;
use KimaiPlugin\HolidayBundle\Repository\AbsenceRepository;
use KimaiPlugin\HolidayBundle\Repository\ExtraShiftRepository;
use KimaiPlugin\HolidayBundle\Repository\LocationExceptionRepository;
use KimaiPlugin\HolidayBundle\Repository\LocationUserRepository;
use KimaiPlugin\HolidayBundle\Repository\PublicHolidayGroupRepository;
use KimaiPlugin\HolidayBundle\Repository\PublicHolidayRepository;
use KimaiPlugin\HolidayBundle\Repository\WorkdayShiftRepository;
use KimaiPlugin\HolidayBundle\Service\AbsenceWorkdayHelper;
use KimaiPlugin\HolidayBundle\Service\AvailabilityCalculator;
use KimaiPlugin\HolidayBundle\Service\LocationSchedule;
use KimaiPlugin\HolidayBundle\Service\UserWorkContract;
use KimaiPlugin\HolidayBundle\Service\WorkdayShiftOverrides;
use KimaiPlugin\HolidayBundle\Tests\Support\AbsenceBuilder;
use KimaiPlugin\HolidayBundle\Tests\Support\ExtraShiftBuilder;
use KimaiPlugin\HolidayBundle\Tests\Support\LocationBuilder;
use KimaiPlugin\HolidayBundle\Tests\Support\UserBuilder;
use KimaiPlugin\HolidayBundle\Tests\Support\WorkdayShiftBuilder;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class AvailabilityCalculatorTest extends TestCase
{
    private User $manuel;
    private User $moritz;
    private User $chef;
    private ?WorkdayShiftOverrides $overrides = null;
    /** @var WorkdayShift[] */
    private array $workdayShifts = [];

    protected function setUp(): void
    {
        $this->manuel = UserBuilder::make()->wednesdayToSaturday(7 * 3600, 9 * 3600)->holidayGroup('1')->build();
        $this->manuel->setAlias('Manuel');
        $this->moritz = UserBuilder::make()->fridaySaturday(8 * 3600)->holidayGroup('1')->build();
        $this->moritz->setAlias('Moritz');
        $this->chef = UserBuilder::make()->build();
        $this->chef->setAlias('Chef');
    }

    public function testFixedPresenceFollowsContract(): void
    {
        $days = $this->forWeek();

        self::assertSame(['Manuel'], $this->names($days['2026-10-28']->present));
        self::assertSame(PresenceKind::FIXED, $days['2026-10-28']->present[0]->kind);
        self::assertSame(['Manuel', 'Moritz'], $this->names($days['2026-10-30']->present));
        self::assertCount(7, $days);
    }

    public function testApprovedShiftMovesPresence(): void
    {
        $shiftRepo = $this->createStub(WorkdayShiftRepository::class);
        $shiftRepo->method('findApprovedForUserBetween')->willReturnCallback(
            fn ($user) => $user === $this->moritz ? [WorkdayShiftBuilder::approved($user, '2026-10-30', '2026-10-29')] : []
        );
        $this->overrides = new WorkdayShiftOverrides($shiftRepo);

        $days = $this->forWeek();

        self::assertSame(['Manuel', 'Moritz'], $this->names($days['2026-10-29']->present)); // T
        self::assertSame(PresenceKind::FIXED, $days['2026-10-29']->present[1]->kind);
        self::assertSame(['Manuel'], $this->names($days['2026-10-30']->present)); // F
    }

    public function testApprovedShiftShowsAsShiftedOnTarget(): void
    {
        $shift = WorkdayShiftBuilder::approved($this->moritz, '2026-10-30', '2026-10-29');
        $this->useShifts([$shift]);

        $days = $this->forWeek();

        self::assertSame(['Manuel', 'Moritz'], $this->names($days['2026-10-29']->present));
        self::assertSame(PresenceKind::FIXED, $days['2026-10-29']->present[0]->kind);
        self::assertSame(PresenceKind::SHIFTED, $days['2026-10-29']->present[1]->kind);
        self::assertSame('2026-10-30', $days['2026-10-29']->present[1]->shift?->getFromDate()?->format('Y-m-d'));
        self::assertSame(['Manuel'], $this->names($days['2026-10-30']->present));
        self::assertSame([], $days['2026-10-29']->pending);
    }

    public function testHalfDayAbsenceOnShiftedTargetStaysShifted(): void
    {
        $this->useShifts([WorkdayShiftBuilder::approved($this->moritz, '2026-10-30', '2026-10-29')]);
        $half = AbsenceBuilder::vacation($this->moritz, '2026-10-29', '2026-10-29')->setHalfDay(true);

        $entry = $this->forWeek([$half])['2026-10-29']->present[1];

        self::assertSame(PresenceKind::SHIFTED, $entry->kind);
        self::assertTrue($entry->halfDay);
    }

    public function testRequestedShiftIsPendingOnBothDays(): void
    {
        $shift = WorkdayShiftBuilder::requested($this->moritz, '2026-10-30', '2026-10-29');
        $this->useShifts([$shift]);

        $days = $this->forWeek();

        foreach (['2026-10-29', '2026-10-30'] as $key) {
            self::assertCount(1, $days[$key]->pending);
            self::assertSame($this->moritz, $days[$key]->pending[0]['user']);
            self::assertSame($shift, $days[$key]->pending[0]['shift']);
        }
        self::assertSame(['Manuel'], $this->names($days['2026-10-29']->present));
        self::assertSame(['Manuel', 'Moritz'], $this->names($days['2026-10-30']->present));
        self::assertSame(PresenceKind::FIXED, $days['2026-10-30']->present[1]->kind);
    }

    public function testShiftedBeatsExtraShiftOrdering(): void
    {
        $this->useShifts([WorkdayShiftBuilder::approved($this->moritz, '2026-10-30', '2026-10-29')]);

        $days = $this->forWeek([], [ExtraShiftBuilder::on($this->chef, '2026-10-29')]);

        self::assertSame(['Manuel', 'Moritz', 'Chef'], $this->names($days['2026-10-29']->present));
        self::assertSame(
            [PresenceKind::FIXED, PresenceKind::SHIFTED, PresenceKind::EXTRA_SHIFT],
            array_map(static fn ($e) => $e->kind, $days['2026-10-29']->present)
        );
    }

    /** @param WorkdayShift[] $shifts */
    private function useShifts(array $shifts): void
    {
        $this->workdayShifts = $shifts;
        $repo = $this->createStub(WorkdayShiftRepository::class);
        $repo->method('findApprovedForUserBetween')->willReturnCallback(
            static fn ($user) => array_values(array_filter($shifts, static fn (WorkdayShift $s): bool => $s->getUser() === $user && $s->getStatus() === AbsenceStatus::APPROVED))
        );
        $this->overrides = new WorkdayShiftOverrides($repo);
    }

    public function testClosedDaysHaveNoPresence(): void
    {
        $day = $this->forWeek()['2026-10-26'];

        self::assertSame([], $day->present);
        self::assertFalse($day->isOpen());
        self::assertFalse($day->isUnstaffed());
    }

    public function testApprovedAbsenceMovesToAbsent(): void
    {
        $days = $this->forWeek([AbsenceBuilder::vacation($this->manuel, '2026-10-28', '2026-10-29')]);

        self::assertSame([], $days['2026-10-28']->present);
        self::assertSame($this->manuel, $days['2026-10-28']->absent[0]['user']);
        self::assertCount(1, $days['2026-10-29']->absent);
        self::assertSame($this->manuel, $days['2026-10-29']->absent[0]['user']);
        self::assertSame(['Manuel', 'Moritz'], $this->names($days['2026-10-30']->present));
        self::assertSame([], $days['2026-10-30']->absent);
    }

    public function testUnstaffedWhenNobodyLeft(): void
    {
        $days = $this->forWeek([AbsenceBuilder::vacation($this->manuel, '2026-10-28', '2026-10-28')]);

        self::assertTrue($days['2026-10-28']->isUnstaffed());
        self::assertFalse($days['2026-10-30']->isUnstaffed());
    }

    public function testHalfDayAbsenceCountsAsPresentWithHint(): void
    {
        $absence = AbsenceBuilder::vacation($this->manuel, '2026-10-28', '2026-10-28')->setHalfDay(true);
        $day = $this->forWeek([$absence])['2026-10-28'];

        self::assertCount(1, $day->present);
        self::assertTrue($day->present[0]->halfDay);
        self::assertSame(PresenceKind::FIXED, $day->present[0]->kind);
        self::assertSame([], $day->absent);
        self::assertFalse($day->isUnstaffed());
    }

    public function testExtraShiftMakesPresent(): void
    {
        $shift = ExtraShiftBuilder::on($this->moritz, '2026-10-29');
        $day = $this->forWeek([], [$shift])['2026-10-29'];

        self::assertSame(['Manuel', 'Moritz'], $this->names($day->present));
        self::assertSame(PresenceKind::EXTRA_SHIFT, $day->present[1]->kind);
        self::assertSame($shift, $day->present[1]->extraShift);
    }

    public function testPendingRequestListedButStillPresent(): void
    {
        $day = $this->forWeek([AbsenceBuilder::requested($this->manuel, '2026-10-28', '2026-10-28')])['2026-10-28'];

        self::assertSame(['Manuel'], $this->names($day->present));
        self::assertSame([], $day->absent);
        self::assertCount(1, $day->pending);
        self::assertSame($this->manuel, $day->pending[0]['user']);
    }

    public function testMemberWithoutContractIsNotPresent(): void
    {
        $days = $this->forWeek([], [ExtraShiftBuilder::on($this->chef, '2026-10-31')]);

        self::assertNotContains('Chef', $this->names($days['2026-10-28']->present));
        self::assertContains('Chef', $this->names($days['2026-10-31']->present));
        self::assertSame(PresenceKind::EXTRA_SHIFT, $days['2026-10-31']->present[2]->kind);
    }

    public function testOpenExceptionOnHolidayLeavesDayUnstaffedUntilExtraShift(): void
    {
        $location = LocationBuilder::make()->holidayGroup(new PublicHolidayGroup())
            ->open(3, '14:00', '21:00')->open(5, '14:00', '21:00')
            ->openOn('2026-05-01', '12:00', '18:00')
            ->build();
        $from = new \DateTimeImmutable('2026-05-01');
        $holiday = (new PublicHoliday())->setDate($from)->setName('Staatsfeiertag')->setHalfDay(false);

        $plain = $this->calculator($location, [], [], [$holiday])->forPeriod($location, $from, $from)['2026-05-01'];
        self::assertTrue($plain->isOpen());
        self::assertTrue($plain->isUnstaffed());

        $shift = ExtraShiftBuilder::on($this->manuel, '2026-05-01');
        $staffed = $this->calculator($location, [], [$shift], [$holiday])->forPeriod($location, $from, $from)['2026-05-01'];
        self::assertSame(['Manuel'], $this->names($staffed->present));
        self::assertSame(PresenceKind::EXTRA_SHIFT, $staffed->present[0]->kind);
        self::assertFalse($staffed->isUnstaffed());
    }

    public function testRepositoriesQueriedOncePerPeriod(): void
    {
        $location = LocationBuilder::vienna(new PublicHolidayGroup());
        $users = $this->createMock(LocationUserRepository::class);
        $users->expects(self::once())->method('findUsersOf')->willReturn([$this->manuel]);
        $absences = $this->createMock(AbsenceRepository::class);
        $absences->expects(self::once())->method('findVisibleForUsersBetween')->with(self::anything(), self::callback(static fn (\DateTimeInterface $d): bool => $d->format('Y-m-d H:i') === '2026-10-01 00:00'), self::anything())->willReturn([]);
        $shifts = $this->createMock(ExtraShiftRepository::class);
        $shifts->expects(self::once())->method('findForUsersBetween')->with(self::anything(), self::callback(static fn (\DateTimeInterface $d): bool => $d->format('Y-m-d H:i') === '2026-10-01 00:00'), self::anything())->willReturn([]);
        $exceptions = $this->createMock(LocationExceptionRepository::class);
        $exceptions->expects(self::once())->method('findBetween')->willReturn([]);
        $holidays = $this->createMock(PublicHolidayRepository::class);
        $holidays->expects(self::once())->method('findByGroupBetween')->willReturn([]);

        $calculator = new AvailabilityCalculator(
            new \KimaiPlugin\HolidayBundle\Service\LocationSchedule($exceptions, $holidays),
            $users,
            $this->helper(),
            $absences,
            $shifts,
            $this->createStub(WorkdayShiftRepository::class)
        );

        $days = $calculator->forPeriod($location, new \DateTimeImmutable('2026-10-01 12:00'), new \DateTimeImmutable('2026-10-31'));
        self::assertCount(31, $days);
    }

    public function testClosedExceptionDayHasNoPresenceButKeepsAbsences(): void
    {
        $location = LocationBuilder::vienna(new PublicHolidayGroup());
        $location->addException((new \KimaiPlugin\HolidayBundle\Entity\LocationException())
            ->setDate(new \DateTimeImmutable('2026-10-28'))
            ->setKind(\KimaiPlugin\HolidayBundle\Enum\LocationExceptionKind::CLOSED));
        $from = new \DateTimeImmutable('2026-10-28');

        $plain = $this->calculator($location, [], [])->forPeriod($location, $from, $from)['2026-10-28'];
        self::assertFalse($plain->isOpen());
        self::assertSame([], $plain->present);

        $vacation = AbsenceBuilder::vacation($this->manuel, '2026-10-28', '2026-10-28');
        $day = $this->calculator($location, [$vacation], [])->forPeriod($location, $from, $from)['2026-10-28'];
        self::assertFalse($day->isOpen());
        self::assertSame([], $day->present);
        self::assertSame($this->manuel, $day->absent[0]['user']);
        self::assertFalse($day->isUnstaffed());
    }

    public function testMemberWithoutContractAppearsInAbsentOnVacation(): void
    {
        $day = $this->forWeek([AbsenceBuilder::vacation($this->chef, '2026-10-28', '2026-10-28')])['2026-10-28'];

        self::assertSame($this->chef, $day->absent[0]['user']);
        self::assertNotContains('Chef', $this->names($day->present));
    }

    public function testFullDayAbsenceBeatsExtraShift(): void
    {
        $day = $this->forWeek(
            [AbsenceBuilder::vacation($this->moritz, '2026-10-29', '2026-10-29')],
            [ExtraShiftBuilder::on($this->moritz, '2026-10-29')]
        )['2026-10-29'];

        self::assertCount(1, $day->absent);
        self::assertSame($this->moritz, $day->absent[0]['user']);
        self::assertSame(['Manuel'], $this->names($day->present));
    }

    public function testHalfDayAbsenceOnNonWorkdayWithoutShiftHasNoEffect(): void
    {
        $absence = AbsenceBuilder::vacation($this->moritz, '2026-10-28', '2026-10-28')->setHalfDay(true);
        $day = $this->forWeek([$absence])['2026-10-28'];

        self::assertSame(['Manuel'], $this->names($day->present));
        self::assertSame([], $day->absent);
    }

    /**
     * @param Absence[] $absences
     * @param ExtraShift[] $shifts
     * @return array<string, \KimaiPlugin\HolidayBundle\Service\AvailabilityDay>
     */
    private function forWeek(array $absences = [], array $shifts = []): array
    {
        $location = LocationBuilder::vienna(new PublicHolidayGroup());

        return $this->calculator($location, $absences, $shifts)->forPeriod(
            $location,
            new \DateTimeImmutable('2026-10-26'),
            new \DateTimeImmutable('2026-11-01')
        );
    }

    /**
     * @param Absence[] $absences
     * @param ExtraShift[] $shifts
     * @param PublicHoliday[] $locationHolidays
     */
    private function calculator(Location $location, array $absences, array $shifts, array $locationHolidays = []): AvailabilityCalculator
    {
        $users = $this->createStub(LocationUserRepository::class);
        $users->method('findUsersOf')->willReturn([$this->manuel, $this->moritz, $this->chef]);
        $absenceRepo = $this->createStub(AbsenceRepository::class);
        $absenceRepo->method('findVisibleForUsersBetween')->willReturn($absences);
        $shiftRepo = $this->createStub(ExtraShiftRepository::class);
        $shiftRepo->method('findForUsersBetween')->willReturn($shifts);
        $exceptions = $this->createStub(LocationExceptionRepository::class);
        $exceptions->method('findBetween')->willReturn($location->getExceptions()->toArray());
        $holidays = $this->createStub(PublicHolidayRepository::class);
        $holidays->method('findByGroupBetween')->willReturn($locationHolidays);

        $workdayShiftRepo = $this->createStub(WorkdayShiftRepository::class);
        $workdayShiftRepo->method('findVisibleForUsersBetween')->willReturn($this->workdayShifts);

        return new AvailabilityCalculator(new LocationSchedule($exceptions, $holidays), $users, $this->helper(), $absenceRepo, $shiftRepo, $workdayShiftRepo);
    }

    private function helper(): AbsenceWorkdayHelper
    {
        $group = new PublicHolidayGroup();
        (new \ReflectionProperty(PublicHolidayGroup::class, 'id'))->setValue($group, 1);
        $groups = $this->createStub(PublicHolidayGroupRepository::class);
        $groups->method('find')->willReturn($group);

        $holiday = (new PublicHoliday())->setDate(new \DateTimeImmutable('2026-05-01'))->setName('Staatsfeiertag')->setHalfDay(false);
        $holidays = $this->createStub(PublicHolidayRepository::class);
        $holidays->method('findByGroupAndYear')->willReturnCallback(
            static fn ($g, int $year) => $year === 2026 ? [$holiday] : []
        );

        $factory = new WorkingTimeModeFactory([new WorkingTimeModeNone(), new WorkingTimeModeDay()], new NullLogger());

        return new AbsenceWorkdayHelper(new UserWorkContract($factory, $groups, $this->overrides), $holidays);
    }

    /**
     * @param \KimaiPlugin\HolidayBundle\Service\PresenceEntry[] $present
     * @return string[]
     */
    private function names(array $present): array
    {
        return array_map(static fn ($e) => $e->user->getDisplayName(), $present);
    }
}
