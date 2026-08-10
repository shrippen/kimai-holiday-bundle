<?php

namespace KimaiPlugin\HolidayBundle\EventSubscriber;

use App\Event\WorkingTimeYearEvent;
use App\WorkingTime\Model\DayAddon;
use KimaiPlugin\HolidayBundle\Enum\AbsenceType;
use KimaiPlugin\HolidayBundle\Enum\CalculationMode;
use KimaiPlugin\HolidayBundle\Repository\AbsenceRepository;
use KimaiPlugin\HolidayBundle\Repository\PublicHolidayRepository;
use KimaiPlugin\HolidayBundle\Service\AbsenceWorkdayHelper;
use KimaiPlugin\HolidayBundle\Service\HolidayConfiguration;
use KimaiPlugin\HolidayBundle\Service\UserWorkContract;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Injects approved absences and public holidays into Kimai's built-in Arbeitszeiten year view.
 * Past/today days affect Soll/Ist; future days are shown as markers only (icons) so month totals stay correct.
 */
class WorkingTimeYearSubscriber implements EventSubscriberInterface
{
    public const PUBLIC_HOLIDAY_ICON = 'public-holiday';

    public function __construct(
        private readonly AbsenceRepository $absenceRepository,
        private readonly PublicHolidayRepository $publicHolidayRepository,
        private readonly UserWorkContract $userWorkContract,
        private readonly HolidayConfiguration $configuration,
        private readonly TranslatorInterface $translator,
        private readonly AbsenceWorkdayHelper $workdayHelper,
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

                $dayAbsences = [];
                foreach ($absences as $absence) {
                    if ($absence->coversDate($dayDate) && $this->workdayHelper->isAbsenceApplicableDay($user, $dayDate)) {
                        $dayAbsences[] = $absence;
                    }
                }
                $hasPublicHoliday = isset($publicHolidays[$key]);

                if ($dayAbsences === [] && !$hasPublicHoliday) {
                    continue;
                }

                $expected = $workingTime->getExpectedTime();

                // Kimai leaves expected=0 for days after "now"; restore contract hours for future markers.
                if ($isFuture && $expected <= 0) {
                    $expected = $this->userWorkContract->getExpectedSecondsForDate($user, $dayDate);
                    if ($expected > 0) {
                        $workingTime->setExpectedTime($expected);
                    }
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

                    $seconds = $this->absenceSeconds($absence, $expected, $workingTime->getActualTime());
                    if ($seconds <= 0) {
                        continue;
                    }

                    $mode = $this->configuration->getCalculationMode($type);

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

                if ($hasPublicHoliday && $expected > 0) {
                    $ph = $publicHolidays[$key];
                    $phSeconds = $ph->isHalfDay() ? (int) floor($expected / 2) : $expected;
                    $title = $ph->getName() ?? $this->translator->trans('menu.public_holidays', [], 'messages');
                    $mode = $this->configuration->getPublicHolidayCalculationMode();

                    if ($isFuture) {
                        if ($mode === CalculationMode::REDUCE) {
                            if ($ph->isHalfDay() && method_exists($workingTime, 'halveExpectedTime')) {
                                $workingTime->halveExpectedTime();
                            } elseif (method_exists($workingTime, 'emptyExpectedTime') && !$ph->isHalfDay()) {
                                $workingTime->emptyExpectedTime();
                            } else {
                                $workingTime->setExpectedTime(max(0, $expected - $phSeconds));
                            }
                        }
                        $day->addAddon(new DayAddon($title, 0, $phSeconds, self::PUBLIC_HOLIDAY_ICON));
                        continue;
                    }

                    if ($mode === CalculationMode::COMPENSATE) {
                        $day->addAddon(new DayAddon($title, $phSeconds, $phSeconds, self::PUBLIC_HOLIDAY_ICON));
                    } else {
                        if ($ph->isHalfDay() && method_exists($workingTime, 'halveExpectedTime')) {
                            $workingTime->halveExpectedTime();
                        } elseif (method_exists($workingTime, 'emptyExpectedTime')) {
                            $workingTime->emptyExpectedTime();
                        } else {
                            $workingTime->setExpectedTime(max(0, $expected - $phSeconds));
                        }
                        $day->addAddon(new DayAddon($title, 0, $phSeconds, self::PUBLIC_HOLIDAY_ICON));
                    }
                }
            }
        }
    }

    private function absenceSeconds(object $absence, int $baseExpected, int $timesheetSeconds): int
    {
        if ($absence->getType() === AbsenceType::OTHER && $absence->getDuration() !== null) {
            return $absence->getDuration();
        }

        if (\in_array($absence->getType(), [AbsenceType::SICKNESS, AbsenceType::SICKNESS_RELATIVE], true)) {
            return max(0, $baseExpected - $timesheetSeconds);
        }

        if ($absence->isHalfDay()) {
            return (int) floor($baseExpected / 2);
        }

        return $baseExpected;
    }
}
