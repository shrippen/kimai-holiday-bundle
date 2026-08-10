<?php

namespace KimaiPlugin\HolidayBundle\Controller;

use App\Controller\AbstractController;
use App\Entity\Team;
use App\Entity\User;
use App\Repository\TeamRepository;
use App\Repository\UserRepository;
use App\Utils\PageSetup;
use KimaiPlugin\HolidayBundle\Enum\AbsenceType;
use KimaiPlugin\HolidayBundle\Repository\AbsenceRepository;
use KimaiPlugin\HolidayBundle\Service\AbsenceWorkdayHelper;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/holiday')]
class AbsenceCalendarController extends AbstractController
{
    public function __construct(
        private readonly AbsenceRepository $absenceRepository,
        private readonly UserRepository $userRepository,
        private readonly TeamRepository $teamRepository,
        private readonly AbsenceWorkdayHelper $workdayHelper,
    ) {
    }

    #[Route(path: '/absence-calendar/{year}', name: 'holiday_absence_calendar', defaults: ['year' => null], methods: ['GET'], requirements: ['year' => '\d+'])]
    #[IsGranted('absence')]
    public function index(Request $request, ?int $year = null): Response
    {
        $year ??= (int) date('Y');

        $users = $this->resolveUsers($request);
        $from = new \DateTimeImmutable(sprintf('%d-01-01', $year));
        $to = new \DateTimeImmutable(sprintf('%d-12-31', $year));

        $absences = $this->absenceRepository->findApprovedForUsersBetween($users, $from, $to);

        $months = [];
        for ($month = 1; $month <= 12; ++$month) {
            $monthStart = new \DateTimeImmutable(sprintf('%d-%02d-01', $year, $month));
            $grid = [];
            foreach ($users as $user) {
                $grid[$user->getId()] = [
                    'user' => $user,
                    'days' => [],
                ];
            }
            $months[$month] = [
                'date' => $monthStart,
                'daysInMonth' => (int) $monthStart->format('t'),
                'grid' => $grid,
            ];
        }

        foreach ($absences as $absence) {
            $uid = $absence->getUser()?->getId();
            if ($uid === null) {
                continue;
            }

            $type = $absence->getType();
            $entry = [
                'type' => $type->value,
                'icon' => $type->icon(),
                'label' => $type->label(),
            ];

            $cursor = $absence->getStartDate();
            $end = $absence->getEndDate();
            if ($cursor === null || $end === null) {
                continue;
            }

            while ($cursor <= $end) {
                $member = $absence->getUser();
                if (
                    $member !== null
                    && (int) $cursor->format('Y') === $year
                    && $this->workdayHelper->isAbsenceApplicableDay($member, $cursor)
                ) {
                    $month = (int) $cursor->format('n');
                    $day = (int) $cursor->format('j');
                    if (isset($months[$month]['grid'][$uid])) {
                        $months[$month]['grid'][$uid]['days'][$day][] = $entry;
                    }
                }
                $cursor = $cursor->modify('+1 day');
            }
        }

        $teams = $this->isGranted('view_other_absence') || $this->isGranted('view_team_absence')
            ? $this->teamRepository->findAll()
            : [];

        $page = new PageSetup('menu.absence_calendar');

        return $this->render('@Holiday/report/absence_calendar.html.twig', [
            'page_setup' => $page,
            'year' => $year,
            'months' => $months,
            'teams' => $teams,
            'selected_team' => $request->query->getInt('team'),
            'type_legend' => array_map(
                static fn (AbsenceType $type): array => [
                    'icon' => $type->icon(),
                    'label' => $type->label(),
                ],
                AbsenceType::cases()
            ),
        ]);
    }

    /** @deprecated Keep old month URLs working */
    #[Route(path: '/absence-calendar/{year}/{month}', name: 'holiday_absence_calendar_month', methods: ['GET'], requirements: ['year' => '\d{4}', 'month' => '\d{1,2}'])]
    #[IsGranted('absence')]
    public function monthRedirect(Request $request, int $year, int $month): RedirectResponse
    {
        return $this->redirectToRoute('holiday_absence_calendar', [
            'year' => $year,
            'team' => $request->query->getInt('team') ?: null,
        ]);
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

        $teamId = $request->query->getInt('team');
        if ($teamId > 0) {
            $team = $this->teamRepository->find($teamId);
            if ($team instanceof Team) {
                return $team->getUsers();
            }
        }

        if ($this->isGranted('view_other_absence')) {
            return array_values(array_filter(
                $this->userRepository->findAll(),
                static fn (User $u): bool => method_exists($u, 'isEnabled') ? $u->isEnabled() : true
            ));
        }

        $users = [$current];
        foreach ($current->getTeams() as $team) {
            if (method_exists($current, 'isTeamlead') && $current->isTeamlead($team)) {
                foreach ($team->getUsers() as $member) {
                    $users[$member->getId()] = $member;
                }
            }
        }

        return array_values($users);
    }
}
