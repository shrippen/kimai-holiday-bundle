<?php

namespace KimaiPlugin\HolidayBundle\API;

use App\Entity\User;
use App\Repository\UserRepository;
use KimaiPlugin\HolidayBundle\Entity\Absence;
use KimaiPlugin\HolidayBundle\Enum\AbsenceType;
use KimaiPlugin\HolidayBundle\Repository\AbsenceRepository;
use KimaiPlugin\HolidayBundle\Repository\PublicHolidayRepository;
use KimaiPlugin\HolidayBundle\Service\AbsenceApprovalService;
use KimaiPlugin\HolidayBundle\Service\UserWorkContract;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route(path: '/holiday')]
class AbsenceApiController extends AbstractController
{
    public function __construct(
        private readonly AbsenceRepository $absenceRepository,
        private readonly AbsenceApprovalService $approvalService,
        private readonly UserRepository $userRepository,
        private readonly UserWorkContract $userWorkContract,
        private readonly PublicHolidayRepository $publicHolidayRepository,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route(path: '/absences/types', name: 'api_holiday_absence_types', methods: ['GET'])]
    #[IsGranted('absence')]
    public function types(): JsonResponse
    {
        $types = [];
        foreach (AbsenceType::cases() as $type) {
            $types[] = [
                'value' => $type->value,
                'label' => $this->translator->trans($type->label()),
                'requiresApproval' => $type->requiresApproval(),
            ];
        }

        return $this->json($types);
    }

    #[Route(path: '/absences', name: 'api_holiday_absences', methods: ['GET'])]
    #[IsGranted('absence')]
    public function list(Request $request): JsonResponse
    {
        /** @var User $current */
        $current = $this->getUser();
        $year = $request->query->getInt('year', (int) date('Y'));
        $userId = $request->query->getInt('user');
        $user = $current;

        if ($userId > 0 && $userId !== $current->getId()) {
            if (!$this->isGranted('view_other_absence')) {
                throw $this->createAccessDeniedException();
            }
            $user = $this->userRepository->find($userId);
            if (!$user instanceof User) {
                throw $this->createNotFoundException();
            }
            $this->assertCanAccessUser($user);
        }

        $absences = $this->absenceRepository->findByUserAndYear($user, $year);

        return $this->json(array_map([$this, 'serializeAbsence'], $absences));
    }

    #[Route(path: '/absences', name: 'api_holiday_absences_create', methods: ['POST'])]
    #[IsGranted('edit_own_absence')]
    public function create(Request $request): JsonResponse
    {
        /** @var User $current */
        $current = $this->getUser();
        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            return $this->error('holiday.error.invalid_json');
        }

        $user = $current;
        if (!empty($data['user']) && (int) $data['user'] !== $current->getId()) {
            if (!$this->isGranted('approval_other_absence') && !$this->isGranted('edit_other_absence')) {
                throw $this->createAccessDeniedException();
            }
            $user = $this->userRepository->find((int) $data['user']);
            if (!$user instanceof User) {
                throw $this->createNotFoundException();
            }
            $this->assertCanAccessUser($user);
        }

        $type = AbsenceType::VACATION;
        if (isset($data['type'])) {
            $type = AbsenceType::tryFrom((string) $data['type']);
            if ($type === null) {
                return $this->error('holiday.error.invalid_type');
            }
        }

        $start = $this->parseDate($data['startDate'] ?? null);
        $end = isset($data['endDate']) ? $this->parseDate($data['endDate']) : $start;
        if ($start === null || $end === null) {
            return $this->error('holiday.error.invalid_date');
        }

        $duration = null;
        if (isset($data['duration'])) {
            if (!is_numeric($data['duration'])) {
                return $this->error('holiday.error.invalid_duration');
            }
            $duration = (int) $data['duration'];
        }

        $comment = $data['comment'] ?? null;
        if ($comment !== null && !\is_string($comment)) {
            return $this->error('holiday.error.invalid_comment');
        }

        $absence = new Absence();
        $absence->setUser($user);
        $absence->setType($type);
        $absence->setStartDate($start);
        $absence->setEndDate($end);
        $absence->setHalfDay((bool) ($data['halfDay'] ?? false));
        $absence->setDuration($duration);
        $absence->setComment($comment);

        try {
            $this->approvalService->create($absence, $current);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return $this->error($e->getMessage());
        }

        return $this->json($this->serializeAbsence($absence), 201);
    }

    #[Route(path: '/absences/{id}/request', name: 'api_holiday_absence_request', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function requestApproval(Absence $absence): JsonResponse
    {
        $this->assertOwnOrEdit($absence);

        try {
            $this->approvalService->request($absence);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return $this->error($e->getMessage());
        }

        return $this->json($this->serializeAbsence($absence));
    }

    #[Route(path: '/absences/{id}/approve', name: 'api_holiday_absence_approve', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function approve(Absence $absence): JsonResponse
    {
        $this->assertCanApprove($absence);

        try {
            $this->approvalService->approve($absence, $this->getUser());
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return $this->error($e->getMessage());
        }

        return $this->json($this->serializeAbsence($absence));
    }

    #[Route(path: '/absences/{id}/reject', name: 'api_holiday_absence_reject', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function reject(Absence $absence): JsonResponse
    {
        $this->assertCanApprove($absence);

        try {
            $this->approvalService->reject($absence, $this->getUser());
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return $this->error($e->getMessage());
        }

        return $this->json($this->serializeAbsence($absence));
    }

    #[Route(path: '/public-holidays', name: 'api_holiday_public_holidays', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
    public function publicHolidays(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $year = $request->query->getInt('year', (int) date('Y'));
        $group = $this->userWorkContract->getPublicHolidayGroup($user);
        if ($group === null) {
            return $this->json([]);
        }

        $holidays = $this->publicHolidayRepository->findByGroupAndYear($group, $year);

        return $this->json(array_map(static fn ($h) => [
            'id' => $h->getId(),
            'date' => $h->getDate()?->format('Y-m-d'),
            'name' => $h->getName(),
            'halfDay' => $h->isHalfDay(),
            'group' => $group->getName(),
        ], $holidays));
    }

    #[Route(path: '/public-holidays/calendar', name: 'api_holiday_public_holidays_calendar', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
    public function publicHolidaysCalendar(Request $request): JsonResponse
    {
        return $this->publicHolidays($request);
    }

    private function assertOwnOrEdit(Absence $absence): void
    {
        if ($absence->getUser() === $this->getUser() && $this->isGranted('edit_own_absence')) {
            return;
        }
        if ($absence->getUser() !== $this->getUser() && $this->isGranted('edit_other_absence') && $this->canAccessUser($absence->getUser())) {
            return;
        }

        throw $this->createAccessDeniedException();
    }

    private function assertCanApprove(Absence $absence): void
    {
        $own = $absence->getUser() === $this->getUser();
        if ($own && $this->isGranted('approve_own_absence')) {
            return;
        }
        if (!$own && $this->isGranted('approve_other_absence') && $this->canAccessUser($absence->getUser())) {
            return;
        }

        throw $this->createAccessDeniedException();
    }

    /**
     * Same scope as the web UI: Kimai's "access_user" rule (view_all_data or team lead of the user).
     */
    private function canAccessUser(?User $user): bool
    {
        return $user !== null && ($user === $this->getUser() || $this->isGranted('access_user', $user));
    }

    private function assertCanAccessUser(User $user): void
    {
        if (!$this->canAccessUser($user)) {
            throw $this->createAccessDeniedException();
        }
    }

    private function parseDate(mixed $value): ?\DateTimeImmutable
    {
        if ($value === null) {
            return new \DateTimeImmutable('today');
        }
        if (!\is_string($value)) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value) {
            return null;
        }

        return $date;
    }

    private function error(string $message): JsonResponse
    {
        return $this->json(['message' => $this->translator->trans($message, [], 'flashmessages')], 400);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeAbsence(Absence $absence): array
    {
        return [
            'id' => $absence->getId(),
            'user' => $absence->getUser()?->getId(),
            'type' => $absence->getType()->value,
            'status' => $absence->getStatus()->value,
            'startDate' => $absence->getStartDate()?->format('Y-m-d'),
            'endDate' => $absence->getEndDate()?->format('Y-m-d'),
            'halfDay' => $absence->isHalfDay(),
            'duration' => $absence->getDuration(),
            'comment' => $absence->getComment(),
        ];
    }
}
