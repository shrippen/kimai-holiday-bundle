<?php

namespace KimaiPlugin\HolidayBundle\Service;

use App\Entity\User;
use App\Repository\TimesheetRepository;
use KimaiPlugin\HolidayBundle\Entity\Absence;
use KimaiPlugin\HolidayBundle\Entity\PublicHoliday;
use KimaiPlugin\HolidayBundle\Enum\CalculationMode;
use KimaiPlugin\HolidayBundle\Repository\AbsenceRepository;
use KimaiPlugin\HolidayBundle\Repository\ManualBookingRepository;
use KimaiPlugin\HolidayBundle\Repository\PublicHolidayRepository;

/**
 * Computes expected / actual / delta working times per day and year balances.
 * Expected hours and vacation entitlement come from Kimai's built-in user contract preferences.
 */
class WorkingTimeCalculator
{
    public function __construct(
        private readonly UserWorkContract $userWorkContract,
        private readonly AbsenceRepository $absenceRepository,
        private readonly PublicHolidayRepository $publicHolidayRepository,
        private readonly ManualBookingRepository $manualBookingRepository,
        private readonly TimesheetRepository $timesheetRepository,
        private readonly HolidayConfiguration $configuration,
        private readonly AbsenceWorkdayHelper $workdayHelper,
        private readonly AbsenceTimesheetService $absenceTimesheetService,
        private readonly DayExpectation $dayExpectation,
    ) {
    }

    /**
     * @return array{
     *   year: int,
     *   months: array<int, array{
     *     month: int,
     *     days: array<string, array{
     *       date: string,
     *       expected: int,
     *       actual: int,
     *       delta: int,
     *       timesheet: int,
     *       absence: int,
     *       publicHoliday: bool,
     *       publicHolidayName: string|null,
     *       absences: list<array{type: string, halfDay: bool}>,
     *       marker: bool
     *     }>,
     *     expected: int,
     *     actual: int,
     *     delta: int
     *   }>,
     *   expected: int,
     *   actual: int,
     *   delta: int,
     *   manualTime: int
     * }
     */
    public function calculateYear(User $user, int $year, ?\DateTimeInterface $until = null): array
    {
        $until = $until ?? new \DateTimeImmutable('now');
        $untilDay = $until instanceof \DateTimeImmutable
            ? $until->setTime(23, 59, 59)
            : \DateTimeImmutable::createFromInterface($until)->setTime(23, 59, 59);

        $yearStart = new \DateTimeImmutable(sprintf('%d-01-01', $year));
        $yearEnd = new \DateTimeImmutable(sprintf('%d-12-31', $year));

        $absences = $this->absenceRepository->findApprovedBetween($user, $yearStart, $yearEnd);

        $publicHolidays = [];
        $group = $this->userWorkContract->getPublicHolidayGroup($user);
        if ($group !== null) {
            foreach ($this->publicHolidayRepository->findByGroupAndYear($group, $year) as $ph) {
                $publicHolidays[$ph->getDate()?->format('Y-m-d')] = $ph;
            }
        }

        $timesheetDurations = $this->getTimesheetDurationsByDay($user, $yearStart, $yearEnd);
        $absenceTimesheetDays = $this->absenceTimesheetService->getAbsenceTimesheetDays($user, $yearStart, $yearEnd);

        $months = [];
        $yearExpected = 0;
        $yearActual = 0;

        for ($month = 1; $month <= 12; ++$month) {
            $daysInMonth = (int) (new \DateTimeImmutable(sprintf('%d-%02d-01', $year, $month)))->format('t');
            $monthData = [
                'month' => $month,
                'days' => [],
                'expected' => 0,
                'actual' => 0,
                'delta' => 0,
            ];

            for ($day = 1; $day <= $daysInMonth; ++$day) {
                $date = new \DateTimeImmutable(sprintf('%d-%02d-%02d', $year, $month, $day));
                $key = $date->format('Y-m-d');
                $includeInBalance = $date <= $untilDay;

                $dayResult = $this->calculateDay(
                    $user,
                    $date,
                    $timesheetDurations[$key] ?? 0,
                    $absences,
                    $publicHolidays[$key] ?? null,
                    $includeInBalance,
                    $absenceTimesheetDays
                );

                $monthData['days'][$key] = $dayResult;

                if ($includeInBalance) {
                    $monthData['expected'] += $dayResult['expected'];
                    $monthData['actual'] += $dayResult['actual'];
                }
            }

            $monthData['delta'] = $monthData['actual'] - $monthData['expected'];
            $yearExpected += $monthData['expected'];
            $yearActual += $monthData['actual'];
            $months[$month] = $monthData;
        }

        $manualTime = $this->manualBookingRepository->sumTimeSecondsUntil($user, $untilDay);

        return [
            'year' => $year,
            'months' => $months,
            'expected' => $yearExpected,
            'actual' => $yearActual + $manualTime,
            'delta' => ($yearActual + $manualTime) - $yearExpected,
            'manualTime' => $manualTime,
        ];
    }

    /**
     * @param Absence[] $absences
     * @param array<int, array<string, int>> $absenceTimesheetDays generated absence timesheets (absence id => [Y-m-d => seconds]);
     *                                                            these are already part of $timesheetSeconds and must not be credited again
     * @return array{
     *   date: string,
     *   expected: int,
     *   actual: int,
     *   delta: int,
     *   timesheet: int,
     *   absence: int,
     *   publicHoliday: bool,
     *   publicHolidayName: string|null,
     *   absences: list<array{type: string, halfDay: bool}>,
     *   marker: bool
     * }
     */
    public function calculateDay(
        User $user,
        \DateTimeImmutable $date,
        int $timesheetSeconds,
        array $absences,
        ?PublicHoliday $publicHoliday,
        bool $includeInBalance = true,
        array $absenceTimesheetDays = [],
    ): array {
        $baseExpected = $this->userWorkContract->getExpectedSecondsForDate($user, $date);
        $absenceMode = fn (Absence $absence) => $this->configuration->getCalculationMode($absence->getType());
        $reduced = 0;
        $absenceSeconds = 0;
        $dayAbsences = [];
        $key = $date->format('Y-m-d');

        foreach ($absences as $absence) {
            if (!$absence->coversDate($date) || !$this->workdayHelper->isAbsenceApplicableDay($user, $date)) {
                continue;
            }

            $dayAbsences[] = [
                'type' => $absence->getType()->value,
                'halfDay' => $absence->isHalfDay(),
            ];

            $balance = $this->dayExpectation->evaluate(
                $baseExpected,
                $timesheetSeconds,
                $absence,
                isset($absenceTimesheetDays[$absence->getId()][$key]),
                null,
                $absenceMode($absence),
                CalculationMode::COMPENSATE
            );
            $absenceSeconds += $balance->credit;
            $reduced += $baseExpected - $balance->expected;
        }

        $holidayBalance = $this->dayExpectation->evaluate(
            $baseExpected,
            $timesheetSeconds,
            null,
            false,
            $publicHoliday,
            CalculationMode::COMPENSATE,
            $this->configuration->getPublicHolidayCalculationMode()
        );
        $absenceSeconds += $holidayBalance->credit;
        $reduced += $baseExpected - $holidayBalance->expected;
        $expected = max(0, $baseExpected - $reduced);

        if (!$includeInBalance) {
            $expected = 0;
            $absenceSeconds = 0;
            $timesheetSeconds = 0;
        }

        $actual = $timesheetSeconds + $absenceSeconds;

        return [
            'date' => $date->format('Y-m-d'),
            'expected' => $expected,
            'actual' => $actual,
            'delta' => $actual - $expected,
            'timesheet' => $timesheetSeconds,
            'absence' => $absenceSeconds,
            'publicHoliday' => $publicHoliday !== null,
            'publicHolidayName' => $publicHoliday?->getName(),
            'absences' => $dayAbsences,
            'marker' => $publicHoliday !== null || $dayAbsences !== [],
        ];
    }

    /**
     * @return array<string, int>
     */
    private function getTimesheetDurationsByDay(User $user, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $timesheets = $this->timesheetRepository->createQueryBuilder('t')
            ->andWhere('t.user = :user')
            ->andWhere('t.begin >= :from')
            ->andWhere('t.begin <= :to')
            ->setParameter('user', $user)
            ->setParameter('from', $from->setTime(0, 0))
            ->setParameter('to', $to->setTime(23, 59, 59))
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($timesheets as $timesheet) {
            $begin = $timesheet->getBegin();
            if ($begin === null) {
                continue;
            }
            $key = $begin->format('Y-m-d');
            $result[$key] = ($result[$key] ?? 0) + (int) ($timesheet->getDuration() ?? 0);
        }

        return $result;
    }
}
