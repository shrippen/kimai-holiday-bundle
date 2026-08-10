<?php

namespace KimaiPlugin\HolidayBundle\Controller;

use App\Controller\AbstractController;
use App\Entity\User;
use KimaiPlugin\HolidayBundle\Enum\AbsenceStatus;
use KimaiPlugin\HolidayBundle\Repository\AbsenceRepository;
use KimaiPlugin\HolidayBundle\Repository\PublicHolidayRepository;
use KimaiPlugin\HolidayBundle\Service\AbsenceWorkdayHelper;
use KimaiPlugin\HolidayBundle\Service\UserWorkContract;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route(path: '/holiday/calendar')]
class CalendarFeedController extends AbstractController
{
    public function __construct(
        private readonly AbsenceRepository $absenceRepository,
        private readonly PublicHolidayRepository $publicHolidayRepository,
        private readonly UserWorkContract $userWorkContract,
        private readonly TranslatorInterface $translator,
        private readonly AbsenceWorkdayHelper $workdayHelper,
    ) {
    }

    #[Route(path: '/absences', name: 'holiday_calendar_absences', methods: ['GET'])]
    #[IsGranted('absence')]
    public function absences(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        [$from, $to] = $this->parseRange($request);

        $users = [$user];
        // With view_other_absence, still default to own calendar feed; team views use the report.

        $absences = $this->absenceRepository->findApprovedForUsersBetween($users, $from, $to);
        // Also include requested for the current user
        $all = $this->absenceRepository->findByUserAndYear($user, (int) $from->format('Y'));
        $events = [];

        foreach ($all as $absence) {
            if (!\in_array($absence->getStatus(), [AbsenceStatus::APPROVED, AbsenceStatus::REQUESTED], true)) {
                continue;
            }
            if ($absence->getEndDate() < $from || $absence->getStartDate() > $to) {
                continue;
            }

            $title = $this->translator->trans($absence->getType()->label());
            if ($absence->getStatus() === AbsenceStatus::REQUESTED) {
                $title .= ' (' . $this->translator->trans('absence.status.requested') . ')';
            }

            $part = 0;
            foreach ($this->workdayHelper->applicableRanges($absence) as [$rangeStart, $rangeEnd]) {
                if ($rangeEnd < $from || $rangeStart > $to) {
                    continue;
                }
                ++$part;
                $events[] = [
                    'id' => 'absence-' . $absence->getId() . '-' . $part,
                    'title' => $title,
                    'start' => $rangeStart->format('Y-m-d'),
                    'end' => $rangeEnd->modify('+1 day')->format('Y-m-d'),
                    'allDay' => true,
                    'color' => $this->colorForType($absence->getType()->value),
                ];
            }
        }

        return $this->json($events);
    }

    #[Route(path: '/public-holidays', name: 'holiday_calendar_public_holidays', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
    public function publicHolidays(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        [$from, $to] = $this->parseRange($request);

        $group = $this->userWorkContract->getPublicHolidayGroup($user);
        if ($group === null) {
            return $this->json([]);
        }

        $holidays = $this->publicHolidayRepository->findByGroupBetween($group, $from, $to);
        $events = [];
        foreach ($holidays as $holiday) {
            $date = $holiday->getDate();
            if ($date === null || $this->workdayHelper->isWeekend($date)) {
                continue;
            }
            $events[] = [
                'id' => 'ph-' . $holiday->getId(),
                'title' => $holiday->getName(),
                'start' => $date->format('Y-m-d'),
                'allDay' => true,
                'color' => '#e74a3b',
            ];
        }

        return $this->json($events);
    }

    /**
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable}
     */
    private function parseRange(Request $request): array
    {
        $start = $request->query->get('start', date('Y-m-01'));
        $end = $request->query->get('end', date('Y-m-t'));

        return [
            new \DateTimeImmutable(substr((string) $start, 0, 10)),
            new \DateTimeImmutable(substr((string) $end, 0, 10)),
        ];
    }

    private function colorForType(string $type): string
    {
        return match ($type) {
            'vacation' => '#1cc88a',
            'sickness', 'sickness_relative' => '#f6c23e',
            'time_off' => '#36b9cc',
            default => '#858796',
        };
    }
}
