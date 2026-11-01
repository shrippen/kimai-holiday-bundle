<?php

namespace KimaiPlugin\HolidayBundle\Controller;

use App\Configuration\LocaleService;
use App\Controller\AbstractController;
use App\Entity\User;
use App\Utils\LocaleFormatter;
use App\Utils\PageSetup;
use KimaiPlugin\HolidayBundle\Entity\Location;
use KimaiPlugin\HolidayBundle\Enum\PresenceKind;
use KimaiPlugin\HolidayBundle\Repository\LocationUserRepository;
use KimaiPlugin\HolidayBundle\Service\AvailabilityCalculator;
use KimaiPlugin\HolidayBundle\Service\AvailabilityDay;
use KimaiPlugin\HolidayBundle\Service\AvailabilityPeriod;
use KimaiPlugin\HolidayBundle\Service\ExtraShiftPermissions;
use KimaiPlugin\HolidayBundle\Service\LocationPermissions;
use KimaiPlugin\HolidayBundle\Service\WorkdayShiftPermissions;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/** Who is present, absent or has an open request at a location, per week or month. */
#[Route(path: '/holiday/availability')]
#[IsGranted('view_availability')]
class AvailabilityController extends AbstractController
{
    private const CELL_FIXED = 'fixed';
    private const CELL_SHIFTED = 'shifted';
    private const CELL_EXTRA = 'extra_shift';
    private const CELL_HALF = 'half_day';
    private const CELL_ABSENT = 'absent';
    private const CELL_PENDING = 'pending';

    public function __construct(
        private readonly AvailabilityCalculator $calculator,
        private readonly LocationPermissions $permissions,
        private readonly ExtraShiftPermissions $extraShiftPermissions,
        private readonly WorkdayShiftPermissions $shiftPermissions,
        private readonly LocationUserRepository $members,
        private readonly LocaleService $localeService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route(path: '', name: 'holiday_availability_current', methods: ['GET'])]
    #[Route(path: '/{year}/week/{week}', name: 'holiday_availability', methods: ['GET'], requirements: ['year' => '\d{4}', 'week' => '\d{1,2}'])]
    public function week(Request $request, ?int $year = null, ?int $week = null): Response
    {
        if ($year === null || $week === null) {
            $period = AvailabilityPeriod::containing(new \DateTimeImmutable('today'), AvailabilityPeriod::WEEK);
        } elseif ($week < 1 || $week > 53) {
            throw $this->createNotFoundException('Invalid week');
        } else {
            $period = AvailabilityPeriod::week($year, $week);
        }

        return $this->renderPeriod($request, $period);
    }

    #[Route(path: '/{year}/month/{month}', name: 'holiday_availability_month', methods: ['GET'], requirements: ['year' => '\d{4}', 'month' => '\d{1,2}'])]
    public function month(Request $request, int $year, int $month): Response
    {
        if ($month < 1 || $month > 12) {
            throw $this->createNotFoundException('Invalid month');
        }

        return $this->renderPeriod($request, AvailabilityPeriod::month($year, $month));
    }

    private function renderPeriod(Request $request, AvailabilityPeriod $period): Response
    {
        /** @var User $current */
        $current = $this->getUser();
        $locations = $this->permissions->visibleLocations();
        $location = $this->resolveLocation($request, $current, $locations);

        $page = new PageSetup($this->translator->trans('holiday.page.availability'));
        $page->setActionName('holiday_availability');
        $page->setHelp('https://github.com/shrippen/kimai-holiday-bundle/blob/main/README.md');

        if ($location === null) {
            $page->setActionPayload(['date' => null, 'can_create' => false]);

            return $this->render('@Holiday/availability/index.html.twig', ['page_setup' => $page, 'location' => null]);
        }

        $days = $this->calculator->forPeriod($location, $period->from, $period->to);
        $openDays = array_filter($days, static fn (AvailabilityDay $day): bool => $day->isOpen());
        $firstOpen = $openDays === [] ? $period->from : reset($openDays)->date;
        $page->setActionPayload([
            'date' => $firstOpen->format('Y-m-d'),
            'can_create' => $this->extraShiftPermissions->canCreateFor($current),
        ]);

        $formatter = new LocaleFormatter($this->localeService, $request->getLocale());
        $label = $period->label(static fn (\DateTimeImmutable $d): string => $formatter->monthName($d, false));

        $rows = [];
        if ($period->unit === AvailabilityPeriod::MONTH) {
            $rows = $this->memberRows($this->members->findUsersOf($location), $openDays);
        }

        $deletable = [];
        $shiftDeletable = [];
        foreach ($days as $day) {
            foreach ($day->pending as $item) {
                $shift = $item['shift'] ?? null;
                if ($shift !== null && $shift->getId() !== null) {
                    $shiftDeletable[$shift->getId()] = $this->shiftPermissions->canDelete($shift);
                }
            }
            foreach ($day->present as $entry) {
                $shift = $entry->shift;
                if ($shift !== null && $shift->getId() !== null) {
                    $shiftDeletable[$shift->getId()] = $this->shiftPermissions->canDelete($shift);
                }
            }
            foreach ($day->present as $entry) {
                $shift = $entry->extraShift;
                if ($entry->kind === PresenceKind::EXTRA_SHIFT && $shift !== null && $shift->getId() !== null) {
                    $deletable[$shift->getId()] = $this->extraShiftPermissions->canDelete($shift);
                }
            }
        }

        return $this->render('@Holiday/availability/index.html.twig', [
            'page_setup' => $page,
            'location' => $location,
            'locations' => $locations,
            'period' => $period,
            'period_label' => $label,
            'nav' => $this->nav($period, $location),
            'days' => $days,
            'open_days' => $openDays,
            'rows' => $rows,
            'deletable' => $deletable,
            'shift_deletable' => $shiftDeletable,
            'today' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
        ]);
    }

    /**
     * Requested location if visible (403 otherwise), else the user's own, else the first visible.
     *
     * @param Location[] $locations
     */
    private function resolveLocation(Request $request, User $current, array $locations): ?Location
    {
        if ($locations === []) {
            return null;
        }

        $requested = $request->query->getInt('location');
        if ($requested > 0) {
            foreach ($locations as $location) {
                if ($location->getId() === $requested && $this->permissions->canViewAvailabilityOf($location)) {
                    return $location;
                }
            }

            throw $this->createAccessDeniedException('You cannot view this location.');
        }

        $own = $this->members->findLocationOf($current);
        foreach ($locations as $location) {
            if ($own !== null && $location->getId() === $own->getId()) {
                return $location;
            }
        }

        return $locations[0];
    }

    /** @return array{unit: string, prev: string, next: string, today: ?string, units: array<string, string>} */
    private function nav(AvailabilityPeriod $period, Location $location): array
    {
        $query = ['location' => $location->getId()];
        $now = new \DateTimeImmutable('today');
        $route = static fn (AvailabilityPeriod $p): string => $p->unit === AvailabilityPeriod::WEEK ? 'holiday_availability' : 'holiday_availability_month';
        $url = fn (AvailabilityPeriod $p): string => $this->generateUrl($route($p), $p->routeParams() + $query);

        $current = AvailabilityPeriod::containing($now, $period->unit);
        $anchor = $period->from;

        return [
            'unit' => $period->unit,
            'prev' => $url($period->prev()),
            'next' => $url($period->next()),
            'today' => $current->from == $period->from ? null : $url($current),
            'units' => [
                AvailabilityPeriod::WEEK => $url(AvailabilityPeriod::containing($anchor, AvailabilityPeriod::WEEK)),
                AvailabilityPeriod::MONTH => $url(AvailabilityPeriod::containing($anchor, AvailabilityPeriod::MONTH)),
            ],
        ];
    }

    /**
     * One row per member, one cell per open day: what the person is that day (or null).
     *
     * @param User[] $users
     * @param array<string, AvailabilityDay> $openDays
     * @return array<int, array{user: User, cells: array<string, ?array{kind: string, icon: string, label: string, pending: ?string[], pending_icon?: ?string}>}>
     */
    private function memberRows(array $users, array $openDays): array
    {
        $rows = [];
        foreach ($users as $user) {
            $cells = [];
            foreach ($openDays as $key => $day) {
                $cells[$key] = $this->cell($user, $day);
            }
            $rows[] = ['user' => $user, 'cells' => $cells];
        }
        usort($rows, static fn (array $a, array $b): int => $a['user']->getDisplayName() <=> $b['user']->getDisplayName());

        return $rows;
    }

    /**
     * 'pending' carries the type labels of the open requests on a day the person is present anyway.
     *
     * @return array{kind: string, icon: string, label: string, pending: ?string[], pending_icon?: ?string}|null
     */
    private function cell(User $user, AvailabilityDay $day): ?array
    {
        foreach ($day->absent as $item) {
            if ($item['user'] === $user) {
                return ['kind' => self::CELL_ABSENT, 'icon' => $item['absence']->getType()->icon(), 'label' => $item['absence']->getType()->label(), 'pending' => null, 'pending_icon' => null];
            }
        }
        // open items of this person (absence first); labels are translation keys
        $open = [];
        foreach ($day->pending as $item) {
            if ($item['user'] !== $user) {
                continue;
            }
            $open[isset($item['absence']) ? 'absence' : 'shift'] = isset($item['absence'])
                ? ['label' => $item['absence']->getType()->label(), 'icon' => $item['absence']->getType()->icon()]
                : ['label' => 'holiday.availability.pending_shift_cell', 'icon' => 'repeat'];
        }
        $open = array_merge(['absence' => null, 'shift' => null], $open);
        $open = array_values(array_filter($open));
        $pending = $open === [] ? null : array_column($open, 'label');
        $pendingIcon = $open[0]['icon'] ?? null;

        foreach ($day->present as $entry) {
            if ($entry->user !== $user) {
                continue;
            }
            $icon = match ($entry->kind) {
                PresenceKind::SHIFTED => 'repeat',
                PresenceKind::EXTRA_SHIFT => 'create',
                PresenceKind::FIXED => 'user',
            };
            $kind = match ($entry->kind) {
                PresenceKind::SHIFTED => self::CELL_SHIFTED,
                PresenceKind::EXTRA_SHIFT => self::CELL_EXTRA,
                PresenceKind::FIXED => self::CELL_FIXED,
            };
            if ($entry->halfDay) {
                return ['kind' => self::CELL_HALF, 'icon' => $icon, 'label' => 'holiday.availability.half_day', 'pending' => $pending, 'pending_icon' => $pendingIcon];
            }

            return ['kind' => $kind, 'icon' => $icon, 'label' => $entry->kind->label(), 'pending' => $pending, 'pending_icon' => $pendingIcon];
        }
        if ($open !== []) {
            return ['kind' => self::CELL_PENDING, 'icon' => $pendingIcon, 'label' => $open[0]['label'], 'pending' => $pending, 'pending_icon' => null];
        }

        return null;
    }
}
