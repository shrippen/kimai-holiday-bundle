<?php

namespace KimaiPlugin\HolidayBundle\Controller;

use App\Configuration\LocaleService;
use App\Controller\AbstractController;
use App\Entity\User;
use App\Form\Type\TeamType;
use App\Utils\LocaleFormatter;
use App\Utils\PageSetup;
use KimaiPlugin\HolidayBundle\Enum\AbsenceStatus;
use KimaiPlugin\HolidayBundle\Enum\AbsenceType;
use KimaiPlugin\HolidayBundle\Repository\AbsenceRepository;
use KimaiPlugin\HolidayBundle\Repository\ExtraShiftRepository;
use KimaiPlugin\HolidayBundle\Repository\PublicHolidayRepository;
use KimaiPlugin\HolidayBundle\Repository\WorkdayShiftRepository;
use KimaiPlugin\HolidayBundle\Service\AbsenceWorkdayHelper;
use KimaiPlugin\HolidayBundle\Service\CalendarMembers;
use KimaiPlugin\HolidayBundle\Service\UserWorkContract;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route(path: '/holiday')]
class AbsenceCalendarController extends AbstractController
{
    public function __construct(
        private readonly AbsenceRepository $absenceRepository,
        private readonly CalendarMembers $calendarMembers,
        private readonly AbsenceWorkdayHelper $workdayHelper,
        private readonly LocaleService $localeService,
        private readonly TranslatorInterface $translator,
        private readonly PublicHolidayRepository $publicHolidayRepository,
        private readonly UserWorkContract $userWorkContract,
        private readonly ExtraShiftRepository $extraShiftRepository,
        private readonly WorkdayShiftRepository $workdayShiftRepository,
    ) {
    }

    #[Route(path: '/absence-calendar/{year}', name: 'holiday_absence_calendar', defaults: ['year' => null], methods: ['GET'], requirements: ['year' => '\d{4}'])]
    #[IsGranted('absence')]
    public function index(Request $request, ?int $year = null): Response
    {
        return $this->renderCalendar($request, $year ?? (int) date('Y'), null);
    }

    #[Route(path: '/absence-calendar/{year}/{month}', name: 'holiday_absence_calendar_month', methods: ['GET'], requirements: ['year' => '\d{4}', 'month' => '\d{1,2}'])]
    #[IsGranted('absence')]
    public function month(Request $request, int $year, int $month): Response
    {
        if ($month < 1 || $month > 12) {
            throw $this->createNotFoundException('Invalid month');
        }

        return $this->renderCalendar($request, $year, $month);
    }

    private function renderCalendar(Request $request, int $year, ?int $onlyMonth): Response
    {
        $users = $this->resolveUsers($request);
        $firstMonth = $onlyMonth ?? 1;
        $lastMonth = $onlyMonth ?? 12;
        $from = new \DateTimeImmutable(sprintf('%d-%02d-01', $year, $firstMonth));
        $to = (new \DateTimeImmutable(sprintf('%d-%02d-01', $year, $lastMonth)))->modify('last day of this month');

        $absences = $this->absenceRepository->findVisibleForUsersBetween($users, $from, $to);
        $holidays = $this->holidaysByUser($users, $from, $to);
        $today = new \DateTimeImmutable('today');

        $months = [];
        for ($month = $firstMonth; $month <= $lastMonth; ++$month) {
            $monthStart = new \DateTimeImmutable(sprintf('%d-%02d-01', $year, $month));
            $daysInMonth = (int) $monthStart->format('t');
            $grid = [];
            foreach ($users as $user) {
                // Per user: days without contract hours or on a full-day holiday are shaded
                $off = [];
                for ($day = 1; $day <= $daysInMonth; ++$day) {
                    $off[$day] = $this->workdayHelper->isNonWorkingDay($user, $monthStart->setDate($year, $month, $day));
                }
                $grid[$user->getId()] = [
                    'user' => $user,
                    'off' => $off,
                    'days' => [],
                    'holidays' => $holidays[$user->getId()][$month] ?? [],
                ];
            }
            $months[$month] = [
                'date' => $monthStart,
                'daysInMonth' => $daysInMonth,
                'today' => $today->format('Y-n') === $year . '-' . $month ? (int) $today->format('j') : null,
                'grid' => $grid,
            ];
        }

        foreach ($absences as $absence) {
            $member = $absence->getUser();
            $uid = $member?->getId();
            $cursor = $absence->getStartDate();
            $end = $absence->getEndDate();
            if ($member === null || $uid === null || $cursor === null || $end === null) {
                continue;
            }

            $type = $absence->getType();
            $entry = [
                'type' => $type->value,
                'icon' => $type->icon(),
                'label' => $type->label(),
                'requested' => $absence->getStatus() === AbsenceStatus::REQUESTED,
            ];

            while ($cursor <= $end) {
                $month = (int) $cursor->format('n');
                if (
                    (int) $cursor->format('Y') === $year
                    && isset($months[$month]['grid'][$uid])
                    && $this->workdayHelper->isAbsenceApplicableDay($member, $cursor)
                ) {
                    $months[$month]['grid'][$uid]['days'][(int) $cursor->format('j')][] = $entry;
                }
                $cursor = $cursor->modify('+1 day');
            }
        }

        // Extra shifts: shown on their day even when the day is off for the user
        foreach ($this->extraShiftRepository->findForUsersBetween($users, $from, $to) as $shift) {
            $uid = $shift->getUser()?->getId();
            $date = $shift->getDate();
            $month = $date !== null ? (int) $date->format('n') : 0;
            if ($uid === null || $date === null || !isset($months[$month]['grid'][$uid])) {
                continue;
            }
            $months[$month]['grid'][$uid]['days'][(int) $date->format('j')][] = [
                'type' => 'extra_shift',
                'icon' => 'create',
                'label' => 'holiday.extra_shift.label',
                'requested' => false,
            ];
        }

        // Day shifts: marked on the day moved away from and on the day moved to
        foreach ($this->workdayShiftRepository->findVisibleForUsersBetween($users, $from, $to) as $shift) {
            $uid = $shift->getUser()?->getId();
            $fromDate = $shift->getFromDate();
            $toDate = $shift->getToDate();
            if ($uid === null || $fromDate === null || $toDate === null) {
                continue;
            }
            $requested = $shift->getStatus() === AbsenceStatus::REQUESTED;
            $marks = [
                [$fromDate, 'holiday.shift.calendar.away', ['%to%' => $toDate->format('d.m.')]],
                [$toDate, 'holiday.shift.calendar.to', ['%from%' => $fromDate->format('d.m.')]],
            ];
            foreach ($marks as [$date, $label, $params]) {
                $month = (int) $date->format('n');
                if ((int) $date->format('Y') !== $year || !isset($months[$month]['grid'][$uid])) {
                    continue;
                }
                $months[$month]['grid'][$uid]['days'][(int) $date->format('j')][] = [
                    'type' => 'workday_shift',
                    'icon' => 'repeat',
                    'label' => $this->translator->trans($label, $params),
                    'requested' => $requested,
                ];
            }
        }

        $teams = $this->calendarMembers->teams();
        $selectedTeam = null;
        $teamId = $request->query->getInt('team');
        foreach ($teams as $team) {
            if ($team->getId() === $teamId) {
                $selectedTeam = $team;
            }
        }

        $teamParam = $selectedTeam?->getId();
        $current = new \DateTimeImmutable('first day of this month');
        $monthDate = new \DateTimeImmutable(sprintf('%d-%02d-01', $year, $onlyMonth ?? (int) $current->format('n')));
        if ($onlyMonth !== null) {
            $prev = $monthDate->modify('-1 month');
            $next = $monthDate->modify('+1 month');
            $period = [
                'unit' => 'month',
                'prev' => $this->generateUrl('holiday_absence_calendar_month', ['year' => (int) $prev->format('Y'), 'month' => (int) $prev->format('n'), 'team' => $teamParam]),
                'next' => $this->generateUrl('holiday_absence_calendar_month', ['year' => (int) $next->format('Y'), 'month' => (int) $next->format('n'), 'team' => $teamParam]),
                'today' => $monthDate->format('Y-m') === $current->format('Y-m') ? null : $this->generateUrl('holiday_absence_calendar_month', ['year' => (int) $current->format('Y'), 'month' => (int) $current->format('n'), 'team' => $teamParam]),
            ];
        } else {
            $period = [
                'unit' => 'year',
                'prev' => $this->generateUrl('holiday_absence_calendar', ['year' => $year - 1, 'team' => $teamParam]),
                'next' => $this->generateUrl('holiday_absence_calendar', ['year' => $year + 1, 'team' => $teamParam]),
                'today' => $year === (int) $current->format('Y') ? null : $this->generateUrl('holiday_absence_calendar', ['team' => $teamParam]),
            ];
        }
        $period['units'] = [
            // the month unit opens the current month of the shown year (January when another year is shown)
            'month' => $this->generateUrl('holiday_absence_calendar_month', [
                'year' => $year,
                'month' => $onlyMonth ?? ($year === (int) $current->format('Y') ? (int) $current->format('n') : 1),
                'team' => $teamParam,
            ]),
            'year' => $this->generateUrl('holiday_absence_calendar', ['year' => $year, 'team' => $teamParam]),
        ];

        $teamForm = null;
        if ($teams !== []) {
            $teamForm = $this->createFormForGetRequest(FormType::class, ['team' => $selectedTeam], [
                'action' => $onlyMonth !== null
                    ? $this->generateUrl('holiday_absence_calendar_month', ['year' => $year, 'month' => $onlyMonth])
                    : $this->generateUrl('holiday_absence_calendar', ['year' => $year]),
                'csrf_protection' => false,
            ]);
            $teamForm->add('team', TeamType::class, [
                'label' => false,
                'required' => false,
                'choices' => $teams,
                'placeholder' => 'holiday.calendar.all_teams',
            ]);
        }

        $periodLabel = $onlyMonth !== null
            ? (new LocaleFormatter($this->localeService, $request->getLocale()))->monthName($monthDate, true)
            : (string) $year;

        $page = new PageSetup($this->translator->trans('holiday.page.absence_calendar', ['%period%' => $periodLabel]));
        $page->setActionName('holiday_absence_calendar');
        $page->setActionPayload(['year' => $year]);
        $page->setHelp('https://github.com/shrippen/kimai-holiday-bundle/blob/main/README.md#absence-calendar');

        return $this->render('@Holiday/report/absence_calendar.html.twig', [
            'page_setup' => $page,
            'year' => $year,
            'month' => $onlyMonth,
            'month_date' => $monthDate,
            'months' => $months,
            'selected_team' => $selectedTeam,
            'team_form' => $teamForm?->createView(),
            'period' => $period,
            'type_legend' => array_map(
                static fn (AbsenceType $type): array => [
                    'icon' => $type->icon(),
                    'label' => $type->label(),
                ],
                AbsenceType::cases()
            ),
        ]);
    }

    /**
     * Users of the picked team, or everyone without a team. Unknown team: 404.
     *
     * @return User[]
     */
    private function resolveUsers(Request $request): array
    {
        $teamId = $request->query->getInt('team');
        if ($teamId <= 0) {
            return $this->calendarMembers->users(null);
        }

        foreach ($this->calendarMembers->teams() as $team) {
            if ($team->getId() === $teamId) {
                return $this->calendarMembers->users($team);
            }
        }

        throw $this->createNotFoundException('Unknown team');
    }

    /**
     * Public holiday names per user, month and day from each user's holiday group (groups loaded once).
     * Example: [7 => [10 => [3 => 'Tag der Deutschen Einheit']]] (user 7, October, day 3).
     *
     * @param User[] $users
     * @return array<int, array<int, array<int, string>>>
     */
    private function holidaysByUser(array $users, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $byGroup = [];
        $result = [];

        foreach ($users as $user) {
            $group = $this->userWorkContract->getPublicHolidayGroup($user);
            if ($group === null || $group->getId() === null) {
                continue;
            }

            $gid = $group->getId();
            if (!isset($byGroup[$gid])) {
                $byGroup[$gid] = [];
                foreach ($this->publicHolidayRepository->findByGroupBetween($group, $from, $to) as $holiday) {
                    $date = $holiday->getDate();
                    if ($date === null) {
                        continue;
                    }
                    $byGroup[$gid][(int) $date->format('n')][(int) $date->format('j')] = $holiday->getName();
                }
            }

            $result[$user->getId()] = $byGroup[$gid];
        }

        return $result;
    }
}
