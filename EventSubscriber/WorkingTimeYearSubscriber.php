<?php

namespace KimaiPlugin\HolidayBundle\EventSubscriber;

use App\Entity\User;
use App\Entity\WorkingTime;
use App\Event\WorkingTimeYearEvent;
use App\WorkingTime\Model\Day;
use App\WorkingTime\Model\DayAddon;
use KimaiPlugin\HolidayBundle\Enum\AbsenceType;
use KimaiPlugin\HolidayBundle\Enum\CalculationMode;
use KimaiPlugin\HolidayBundle\Repository\AbsenceRepository;
use KimaiPlugin\HolidayBundle\Repository\PublicHolidayRepository;
use KimaiPlugin\HolidayBundle\Service\AbsenceTimesheetService;
use KimaiPlugin\HolidayBundle\Service\AbsenceWorkdayHelper;
use KimaiPlugin\HolidayBundle\Service\DayExpectation;
use KimaiPlugin\HolidayBundle\Service\HolidayConfiguration;
use KimaiPlugin\HolidayBundle\Service\UserWorkContract;
use KimaiPlugin\HolidayBundle\Service\WorkdayShiftOverrides;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Injects approved absences and public holidays into Kimai's built-in Arbeitszeiten year view.
 * Past/today days affect Soll/Ist; future days only carry markers (icons) so month totals stay correct; shifted days always carry their contract value and a note.
 */
class WorkingTimeYearSubscriber implements EventSubscriberInterface
{
    public const PUBLIC_HOLIDAY_ICON = 'public-holiday';
    private const SHIFT_ICON = 'repeat';
    private const SHIFT_AWAY = 'holiday.shift.addon.away';
    private const SHIFT_TO = 'holiday.shift.addon.to';

    public function __construct(
        private readonly AbsenceRepository $absenceRepository,
        private readonly PublicHolidayRepository $publicHolidayRepository,
        private readonly UserWorkContract $userWorkContract,
        private readonly HolidayConfiguration $configuration,
        private readonly TranslatorInterface $translator,
        private readonly AbsenceWorkdayHelper $workdayHelper,
        private readonly AbsenceTimesheetService $absenceTimesheetService,
        private readonly DayExpectation $dayExpectation,
        private readonly WorkdayShiftOverrides $shiftOverrides,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkingTimeYearEvent::class => ['onWorkingTimeYear', 100],
        ];
    }

    public function onWorkingTimeYear(WorkingTimeYearEvent $event): void
    {
        $year = $event->getYear();
        $user = $year->getUser();
        $until = $event->getUntil();
        $yearStart = \DateTimeImmutable::createFromInterface($year->getYear())->modify('first day of January')->setTime(0, 0);
        $yearEnd = $yearStart->modify('last day of December')->setTime(23, 59, 59);

        $absences = $this->absenceRepository->findApprovedBetween($user, $yearStart, $yearEnd);
        // Auto-created absence timesheets are already part of Kimai's "Ist"; do not credit those days twice.
        $absenceTimesheetDays = $absences !== []
            ? $this->absenceTimesheetService->getAbsenceTimesheetDays($user, $yearStart, $yearEnd)
            : [];

        $publicHolidays = [];
        $group = $this->userWorkContract->getPublicHolidayGroup($user);
        if ($group !== null) {
            foreach ($this->publicHolidayRepository->findByGroupAndYear($group, (int) $yearStart->format('Y')) as $ph) {
                $key = $ph->getDate()?->format('Y-m-d');
                if ($key !== null) {
                    $publicHolidays[$key] = $ph;
                }
            }
        }

        foreach ($year->getMonths() as $month) {
            foreach ($month->getDays() as $day) {
                $workingTime = $day->getWorkingTime();
                if ($workingTime === null || $day->isLocked()) {
                    continue;
                }

                $dayDate = $day->getDay();
                $isFuture = $dayDate > $until;
                $key = $dayDate->format('Y-m-d');
                $base = $this->userWorkContract->getExpectedSecondsForDate($user, $dayDate);

                $dayAbsences = [];
                foreach ($absences as $absence) {
                    if ($absence->coversDate($dayDate) && $this->workdayHelper->isAbsenceApplicableDay($user, $dayDate)) {
                        $dayAbsences[] = $absence;
                    }
                }
                $hasPublicHoliday = isset($publicHolidays[$key]);
                $hasShift = $this->applyShift($day, $workingTime, $user, $dayDate, $base);

                if ($dayAbsences === [] && !$hasPublicHoliday) {
                    continue;
                }

                $expected = $hasShift ? $base : $workingTime->getExpectedTime();

                // Kimai leaves expected=0 for days after "now"; restore contract hours for future markers.
                if ($isFuture && $expected <= 0 && $base > 0) {
                    $expected = $base;
                    $workingTime->setExpectedTime($expected);
                }

                foreach ($dayAbsences as $absence) {
                    $type = $absence->getType();
                    $title = $this->translator->trans($type->label(), [], 'messages');
                    $icon = $type->icon();

                    // Time-off: marker only (does not change Soll/Ist).
                    if ($type === AbsenceType::TIME_OFF) {
                        if ($expected <= 0) {
                            continue;
                        }
                        $day->addAddon(new DayAddon($title, 0, $expected, $icon));
                        continue;
                    }

                    if (isset($absenceTimesheetDays[$absence->getId()][$key])) {
                        $day->addAddon(new DayAddon($title, 0, $absenceTimesheetDays[$absence->getId()][$key], $icon));
                        continue;
                    }

                    $mode = $this->configuration->getCalculationMode($type);
                    $seconds = $this->dayExpectation->evaluate(
                        $base,
                        $workingTime->getActualTime(),
                        $absence,
                        false,
                        null,
                        $mode,
                        CalculationMode::COMPENSATE
                    )->absenceSeconds;
                    if ($seconds <= 0) {
                        continue;
                    }

                    if ($isFuture) {
                        // Preview only: never inflate Ist (addon duration is added to actualTime).
                        if ($mode === CalculationMode::REDUCE) {
                            $workingTime->setExpectedTime(max(0, $expected - $seconds));
                            $expected = $workingTime->getExpectedTime();
                        }
                        $day->addAddon(new DayAddon($title, 0, $seconds, $icon));
                        continue;
                    }

                    if ($mode === CalculationMode::COMPENSATE) {
                        $day->addAddon(new DayAddon($title, $seconds, $seconds, $icon));
                    } else {
                        $workingTime->setExpectedTime(max(0, $expected - $seconds));
                        $day->addAddon(new DayAddon($title, 0, $seconds, $icon));
                        $expected = $workingTime->getExpectedTime();
                    }
                }

                if (!$hasPublicHoliday) {
                    continue;
                }

                $ph = $publicHolidays[$key];
                $mode = $this->configuration->getPublicHolidayCalculationMode();
                $phSeconds = $this->dayExpectation->evaluate($base, 0, null, false, $ph, CalculationMode::COMPENSATE, $mode)->holidaySeconds;
                if ($phSeconds <= 0) {
                    continue;
                }

                $title = $ph->getName() ?? $this->translator->trans('holiday.menu.public_holidays', [], 'messages');

                if ($isFuture) {
                    if ($mode === CalculationMode::REDUCE) {
                        $workingTime->setExpectedTime(max(0, $expected - $phSeconds));
                    }
                    $day->addAddon(new DayAddon($title, 0, $phSeconds, self::PUBLIC_HOLIDAY_ICON));
                    continue;
                }

                if ($mode === CalculationMode::COMPENSATE) {
                    $day->addAddon(new DayAddon($title, $phSeconds, $phSeconds, self::PUBLIC_HOLIDAY_ICON));
                    continue;
                }

                $workingTime->setExpectedTime(max(0, $expected - $phSeconds));
                $day->addAddon(new DayAddon($title, 0, $phSeconds, self::PUBLIC_HOLIDAY_ICON));
            }
        }
    }

    /**
     * Kimai computes past days itself; shifted days need the contract value
     * and a visible note. Returns whether the day carries a shift.
     */
    private function applyShift(Day $day, WorkingTime $workingTime, User $user, \DateTimeImmutable $date, int $base): bool
    {
        $source = $this->shiftOverrides->sourceFor($user, $date);
        $away = $this->shiftOverrides->isShiftedAway($user, $date);
        if ($source === null && !$away) {
            return false;
        }

        $workingTime->setExpectedTime($base);

        $other = $away ? $this->shiftOverrides->targetFor($user, $date) : $source;
        $title = $this->translator->trans(
            $away ? self::SHIFT_AWAY : self::SHIFT_TO,
            [$away ? '%to%' : '%from%' => $other?->format('d.m.Y') ?? ''],
            'messages'
        );
        $day->addAddon(new DayAddon($title, 0, 0, self::SHIFT_ICON));

        return true;
    }
}
