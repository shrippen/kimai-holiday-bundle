<?php

namespace KimaiPlugin\HolidayBundle\Tests\Service;

use App\Entity\User;
use App\WorkingTime\Mode\WorkingTimeModeDay;
use App\WorkingTime\Mode\WorkingTimeModeFactory;
use App\WorkingTime\Mode\WorkingTimeModeNone;
use KimaiPlugin\HolidayBundle\Entity\Absence;
use KimaiPlugin\HolidayBundle\Enum\VacationYearMode;
use KimaiPlugin\HolidayBundle\Repository\AbsenceRepository;
use KimaiPlugin\HolidayBundle\Repository\ManualBookingRepository;
use KimaiPlugin\HolidayBundle\Repository\PublicHolidayGroupRepository;
use KimaiPlugin\HolidayBundle\Repository\PublicHolidayRepository;
use KimaiPlugin\HolidayBundle\Service\AbsenceWorkdayHelper;
use KimaiPlugin\HolidayBundle\Service\HolidayConfiguration;
use KimaiPlugin\HolidayBundle\Service\UserWorkContract;
use KimaiPlugin\HolidayBundle\Service\VacationAccountService;
use KimaiPlugin\HolidayBundle\Service\VacationYear;
use KimaiPlugin\HolidayBundle\Service\VacationYearResolver;
use KimaiPlugin\HolidayBundle\Tests\Support\AbsenceBuilder;
use KimaiPlugin\HolidayBundle\Tests\Support\UserBuilder;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/** Setting "vacation year = calendar year": entry and leaving year pro rata by days. */
final class VacationCalendarYearTest extends TestCase
{
    public function testVacationYearIsTheCalendarYear(): void
    {
        $user = UserBuilder::make()->startingOn('2026-07-15')->build();
        $year = $this->resolver()->forDate($user, new \DateTimeImmutable('2026-10-23'));

        self::assertSame('2026-01-01', $year->start->format('Y-m-d'));
        self::assertSame('2026-12-31', $year->end->format('Y-m-d'));
        self::assertSame(2026, $year->index);
    }

    public function testChainStartsInTheEntryYear(): void
    {
        $user = UserBuilder::make()->startingOn('2026-07-15')->build();
        $resolver = $this->resolver();

        self::assertNull($resolver->previous($user, $resolver->forIndex($user, 2026)));
        self::assertSame(2026, $resolver->previous($user, $resolver->forIndex($user, 2027))?->index);
    }

    public function testEntryYearIsProRataByDays(): void
    {
        // 15.07.–31.12.2026 = 170 of 365 days: 20 × 170 / 365 = 9.32 → 9.5
        $user = UserBuilder::make()->startingOn('2026-07-15')->vacationDays(20)->wednesdayToSaturday(7 * 3600, 9 * 3600)->build();

        self::assertSame(9.5, $this->service([])->calculate($user, $this->year($user, '2026-10-23'))->entitlement);
    }

    public function testNextYearIsFullWithCarryOver(): void
    {
        // 2026: 9.5 − 4 days vacation (21.–24.10.) = 5.5 carried into 2027
        $user = UserBuilder::make()->startingOn('2026-07-15')->vacationDays(20)->wednesdayToSaturday(7 * 3600, 9 * 3600)->build();
        $acc = $this->service([AbsenceBuilder::vacation($user, '2026-10-21', '2026-10-24')])->calculate($user, $this->year($user, '2027-03-01'));

        self::assertSame(20.0, $acc->entitlement);
        self::assertSame(5.5, $acc->carryOver);
        self::assertSame(25.5, $acc->balance);
    }

    public function testLeavingYearIsProRataByDays(): void
    {
        // 01.01.–14.09.2026 = 257 of 365 days: 20 × 257 / 365 = 14.08 → 14.5
        $user = UserBuilder::make()->startingOn('2020-01-01')->endingOn('2026-09-14')->vacationDays(20)->wednesdayToSaturday(7 * 3600, 9 * 3600)->build();

        self::assertSame(14.5, $this->service([])->calculate($user, $this->year($user, '2026-06-01'))->entitlement);
    }

    public function testNoEntitlementBeforeTheEntryYear(): void
    {
        $user = UserBuilder::make()->startingOn('2026-07-15')->vacationDays(20)->wednesdayToSaturday(7 * 3600, 9 * 3600)->build();

        self::assertSame(0.0, $this->service([])->calculate($user, $this->year($user, '2025-06-01'))->entitlement);
    }

    public function testEmploymentYearStaysDefault(): void
    {
        $user = UserBuilder::make()->startingOn('2026-07-15')->build();
        $year = (new VacationYearResolver())->forDate($user, new \DateTimeImmutable('2026-10-23'));

        self::assertSame('2026-07-15', $year->start->format('Y-m-d'));
    }

    private function resolver(): VacationYearResolver
    {
        $configuration = $this->createStub(HolidayConfiguration::class);
        $configuration->method('getVacationYearMode')->willReturn(VacationYearMode::CALENDAR);

        return new VacationYearResolver($configuration);
    }

    private function year(User $user, string $ymd): VacationYear
    {
        return $this->resolver()->forDate($user, new \DateTimeImmutable($ymd));
    }

    /**
     * @param Absence[] $absences
     */
    private function service(array $absences): VacationAccountService
    {
        $factory = new WorkingTimeModeFactory([new WorkingTimeModeNone(), new WorkingTimeModeDay()], new NullLogger());
        $contract = new UserWorkContract($factory, $this->createStub(PublicHolidayGroupRepository::class));
        $helper = new AbsenceWorkdayHelper($contract, $this->createStub(PublicHolidayRepository::class));

        $absenceRepo = $this->createStub(AbsenceRepository::class);
        $absenceRepo->method('findApprovedVacationsBetween')->willReturnCallback(
            static fn (User $u, \DateTimeInterface $from, \DateTimeInterface $to): array => array_values(array_filter(
                $absences,
                static fn (Absence $a): bool => $a->getStartDate() <= $to && $a->getEndDate() >= $from
            ))
        );
        $manualRepo = $this->createStub(ManualBookingRepository::class);
        $manualRepo->method('sumHolidayDaysBetween')->willReturn(0.0);

        return new VacationAccountService($this->resolver(), $contract, $absenceRepo, $manualRepo, $helper);
    }
}
