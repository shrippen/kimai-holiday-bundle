<?php

namespace KimaiPlugin\HolidayBundle\Controller;

use App\Configuration\LocaleService;
use App\Controller\AbstractController;
use App\Entity\Team;
use App\Entity\User;
use App\Form\Type\TeamType;
use App\Repository\TeamRepository;
use App\Repository\UserRepository;
use App\Utils\LocaleFormatter;
use App\Utils\PageSetup;
use KimaiPlugin\HolidayBundle\Enum\AbsenceStatus;
use KimaiPlugin\HolidayBundle\Enum\AbsenceType;
use KimaiPlugin\HolidayBundle\Repository\AbsenceRepository;
use KimaiPlugin\HolidayBundle\Service\AbsenceWorkdayHelper;
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
        private readonly UserRepository $userRepository,
        private readonly TeamRepository $teamRepository,
        private readonly AbsenceWorkdayHelper $workdayHelper,
        private readonly LocaleService $localeService,
        private readonly TranslatorInterface $translator,
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

        $months = [];
        for ($month = $firstMonth; $month <= $lastMonth; ++$month) {
            $monthStart = new \DateTimeImmutable(sprintf('%d-%02d-01', $year, $month));
            $weekend = [];
            $daysInMonth = (int) $monthStart->format('t');
            for ($day = 1; $day <= $daysInMonth; ++$day) {
                $weekend[$day] = $this->workdayHelper->isWeekend($monthStart->setDate($year, $month, $day));
            }
            $grid = [];
            foreach ($users as $user) {
                $grid[$user->getId()] = [
                    'user' => $user,
                    'days' => [],
                ];
            }
            $months[$month] = [
                'date' => $monthStart,
                'daysInMonth' => $daysInMonth,
                'weekend' => $weekend,
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

        $teams = $this->getSelectableTeams();
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
     * Teams the current user may pick: all teams with view_all_data (admins), otherwise
     * teams they lead (view_other_absence) or belong to (view_team_absence).
     *
     * @return Team[]
     */
    private function getSelectableTeams(): array
    {
        /** @var User $current */
        $current = $this->getUser();
        $canOther = $this->isGranted('view_other_absence');
        $canTeam = $this->isGranted('view_team_absence');

        if (!$canOther && !$canTeam) {
            return [];
        }

        if ($canOther && $current->canSeeAllData()) {
            return $this->teamRepository->findAll();
        }

        $teams = [];
        foreach ($current->getTeams() as $team) {
            if ($canTeam || $current->isTeamleadOf($team)) {
                $teams[$team->getId()] = $team;
            }
        }

        return array_values($teams);
    }

    /**
     * @return User[]
     */
    private function resolveUsers(Request $request): array
    {
        /** @var User $current */
        $current = $this->getUser();

        if (!$this->isGranted('view_other_absence') && !$this->isGranted('view_team_absence')) {
            return [$current];
        }

        $teams = $this->getSelectableTeams();
        $teamId = $request->query->getInt('team');
        if ($teamId > 0) {
            foreach ($teams as $team) {
                if ($team->getId() === $teamId) {
                    return array_values(array_filter(
                        $team->getUsers(),
                        static fn (User $u): bool => $u->isEnabled()
                    ));
                }
            }

            throw $this->createAccessDeniedException('You cannot view this team.');
        }

        if ($this->isGranted('view_other_absence')) {
            return array_values(array_filter(
                $this->userRepository->findAll(),
                fn (User $u): bool => $u->isEnabled() && ($u === $current || $this->isGranted('access_user', $u))
            ));
        }

        $users = [$current->getId() => $current];
        foreach ($teams as $team) {
            foreach ($team->getUsers() as $member) {
                if ($member->isEnabled()) {
                    $users[$member->getId()] = $member;
                }
            }
        }

        return array_values($users);
    }
}
