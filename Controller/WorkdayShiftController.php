<?php

namespace KimaiPlugin\HolidayBundle\Controller;

use App\Controller\AbstractController;
use App\Entity\User;
use App\Utils\PageSetup;
use KimaiPlugin\HolidayBundle\Entity\WorkdayShift;
use KimaiPlugin\HolidayBundle\Enum\AbsenceStatus;
use KimaiPlugin\HolidayBundle\Form\WorkdayShiftType;
use KimaiPlugin\HolidayBundle\Repository\WorkdayShiftRepository;
use KimaiPlugin\HolidayBundle\Service\WorkdayShiftPermissions;
use KimaiPlugin\HolidayBundle\Service\WorkdayShiftService;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Day shifts: request (modal), decide in bulk with undo, delete.
 */
#[Route(path: '/holiday/workday-shifts')]
#[IsGranted(new Expression('is_granted("shift_own") or is_granted("shift_other") or is_granted("approve_shift")'))]
class WorkdayShiftController extends AbstractController
{
    use HolidayUiTrait;

    public const CSRF_ACTION = 'holiday_workday_shift_action';
    /** Undo window of approve/reject: 15 minutes, same user, same session. */
    private const UNDO_WINDOW = 900;
    private const UNDO_SESSION_PREFIX = 'holiday.shift_undo.';
    private const LIST_ROUTE = 'holiday_absence_requests';

    public function __construct(
        private readonly WorkdayShiftRepository $repository,
        private readonly WorkdayShiftService $service,
        private readonly WorkdayShiftPermissions $permissions,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route(path: '/create', name: 'holiday_workday_shift_create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        /** @var User $current */
        $current = $this->getUser();
        $chooseUser = $this->isGranted('shift_other');

        $data = ['user' => $current, 'fromDate' => $this->prefillDate($request), 'toDate' => null, 'comment' => null];
        $form = $this->createForm(WorkdayShiftType::class, $data, [
            'action' => $this->generateUrl('holiday_workday_shift_create'),
            'method' => 'POST',
            'attr' => ['data-form-event' => AbsenceController::UPDATE_EVENT],
            'choose_user' => $chooseUser,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $chooseUser ? $form->get('user')->getData() : $current;
            $from = $form->get('fromDate')->getData();
            $to = $form->get('toDate')->getData();
            if (!$user instanceof User || !$from instanceof \DateTimeImmutable || !$to instanceof \DateTimeImmutable) {
                throw $this->createNotFoundException();
            }
            if (!$this->permissions->canCreateFor($user)) {
                throw $this->createAccessDeniedException();
            }

            $shift = (new WorkdayShift())
                ->setUser($user)
                ->setFromDate($from)
                ->setToDate($to)
                ->setComment($form->get('comment')->getData());

            try {
                $this->service->create($shift, $current);
                $this->flashSuccess('holiday.shift.created');

                return $this->formSuccess($request, 'holiday_availability_current');
            } catch (\InvalidArgumentException $e) {
                $this->addFormError($form->get('toDate'), $this->errorKey($e));
            }
        }

        return $this->render('@Holiday/workday_shift/form.html.twig', [
            'page_setup' => new PageSetup('holiday.shift.create'),
            'form' => $form->createView(),
            'title' => $this->translator->trans('holiday.shift.create'),
            'back' => $this->generateUrl('holiday_availability_current'),
            'submit' => 'action.save',
        ]);
    }

    #[Route(path: '/approve', name: 'holiday_workday_shift_bulk_approve', methods: ['POST'])]
    public function bulkApprove(Request $request): Response
    {
        return $this->bulkDecision($request, true);
    }

    #[Route(path: '/reject', name: 'holiday_workday_shift_bulk_reject', methods: ['POST'])]
    public function bulkReject(Request $request): Response
    {
        return $this->bulkDecision($request, false);
    }

    /**
     * Undo of the current user's own approve/reject action: only what bulkDecision() stored in this session,
     * only by the same user, within UNDO_WINDOW seconds, only its shifts and only if unchanged since.
     */
    #[Route(path: '/reopen/{action}', name: 'holiday_workday_shift_reopen', methods: ['POST'], requirements: ['action' => '[a-f0-9]{16}'])]
    public function reopen(Request $request, string $action): Response
    {
        $this->assertCsrf($request);

        $session = $request->getSession();
        $key = self::UNDO_SESSION_PREFIX . $action;
        $entry = $session->get($key);
        $session->remove($key);
        $user = $this->getUser();

        if (!\is_array($entry) || ($entry['user'] ?? null) !== $user?->getId() || (int) ($entry['at'] ?? 0) < time() - self::UNDO_WINDOW) {
            return $this->actionResult($request, $this->translator->trans('holiday.shift.undo.expired'), null, self::LIST_ROUTE, [], 409);
        }

        /** @var array<int, array{status: string, approved_at: string|null}> $before */
        $before = $entry['shifts'];
        $requested = array_map('intval', $request->request->all('ids'));
        if (array_diff($requested, array_keys($before)) !== []) {
            throw $this->createAccessDeniedException('Undo is limited to the shifts of the action');
        }

        $shifts = $this->repository->findBy(['id' => array_keys($before)]);
        $this->assertCanApproveAll($shifts);
        $done = 0;
        $changed = 0;
        foreach ($shifts as $shift) {
            // the decision stored here is the one to undo: the state after it must still be there
            $state = $before[(int) $shift->getId()];
            if ($shift->getStatus()->value !== $state['status']
                || $shift->getApprovedBy()?->getId() !== $user?->getId()
                || $shift->getApprovedAt()?->format('Y-m-d H:i:s') !== $state['approved_at']) {
                ++$changed;
                continue;
            }
            try {
                $this->service->reopen($shift);
                ++$done;
            } catch (\InvalidArgumentException|\RuntimeException) {
                ++$changed;
            }
        }
        $changed += \count($before) - \count($shifts);

        if ($done === 0) {
            return $this->actionResult($request, $this->translator->trans('holiday.shift.undo.changed'), null, self::LIST_ROUTE, [], 409);
        }

        $message = $this->translator->trans('holiday.shift.bulk.reopened', ['%count%' => $done]);
        if ($changed > 0) {
            $message .= ' ' . $this->translator->trans('holiday.shift.undo.skipped', ['%count%' => $changed]);
        }

        return $this->actionResult($request, $message, null, self::LIST_ROUTE, []);
    }

    #[Route(path: '/{id}/delete', name: 'holiday_workday_shift_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, WorkdayShift $shift): Response
    {
        $this->assertCsrf($request);
        if (!$this->permissions->canDelete($shift)) {
            throw $this->createAccessDeniedException();
        }

        /** @var User $current */
        $current = $this->getUser();
        try {
            $this->service->delete($shift, $current);
        } catch (\InvalidArgumentException $e) {
            $key = $this->errorKey($e) ?? 'holiday.shift.bulk.none';

            return $this->actionResult($request, $this->translator->trans($key, [], 'flashmessages'), null, self::LIST_ROUTE, [], 422);
        }

        return $this->actionResult($request, $this->translator->trans('holiday.shift.deleted'), null, self::LIST_ROUTE, []);
    }

    private function bulkDecision(Request $request, bool $approve): Response
    {
        $this->assertCsrf($request);
        $shifts = $this->loadSelected($request);
        $this->assertCanApproveAll($shifts);

        /** @var User $current */
        $current = $this->getUser();
        $done = [];
        $skipped = 0;
        $firstError = null;
        foreach ($shifts as $shift) {
            if ($shift->getStatus() !== AbsenceStatus::REQUESTED) {
                ++$skipped;
                continue;
            }
            try {
                $approve ? $this->service->approve($shift, $current) : $this->service->reject($shift, $current);
                $done[] = $shift;
            } catch (\InvalidArgumentException|\RuntimeException $e) {
                $firstError ??= $this->errorKey($e);
                ++$skipped;
            }
        }

        if ($done === []) {
            $message = $firstError === null
                ? $this->translator->trans('holiday.shift.bulk.none')
                : $this->translator->trans($firstError, [], 'flashmessages');

            return $this->actionResult($request, $message, null, self::LIST_ROUTE, [], 422);
        }

        $message = $this->translator->trans($approve ? 'holiday.shift.bulk.approved' : 'holiday.shift.bulk.rejected', ['%count%' => \count($done)]);
        if ($skipped > 0) {
            $message .= ' ' . $this->translator->trans('holiday.shift.bulk.skipped', ['%count%' => $skipped]);
        }

        $undo = [
            'url' => $this->generateUrl('holiday_workday_shift_reopen', ['action' => $this->rememberUndo($request, $done)]),
            'token' => $this->container->get('security.csrf.token_manager')->getToken(self::CSRF_ACTION)->getValue(),
            'ids' => array_map(static fn (WorkdayShift $s): int => (int) $s->getId(), $done),
        ];

        return $this->actionResult($request, $message, $undo, self::LIST_ROUTE, []);
    }

    /**
     * Stores the undo entry in the session and returns its id; expired entries of earlier actions are dropped.
     *
     * @param list<WorkdayShift> $shifts
     */
    private function rememberUndo(Request $request, array $shifts): string
    {
        $session = $request->getSession();
        foreach (array_keys($session->all()) as $name) {
            if (str_starts_with($name, self::UNDO_SESSION_PREFIX) && (int) ($session->get($name)['at'] ?? 0) < time() - self::UNDO_WINDOW) {
                $session->remove($name);
            }
        }

        $state = [];
        foreach ($shifts as $shift) {
            $state[(int) $shift->getId()] = [
                'status' => $shift->getStatus()->value,
                'approved_at' => $shift->getApprovedAt()?->format('Y-m-d H:i:s'),
            ];
        }

        $action = bin2hex(random_bytes(8));
        $session->set(self::UNDO_SESSION_PREFIX . $action, ['user' => $this->getUser()?->getId(), 'shifts' => $state, 'at' => time()]);

        return $action;
    }

    /**
     * @return list<WorkdayShift>
     */
    private function loadSelected(Request $request): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $request->request->all('ids')),
            static fn (int $id): bool => $id > 0
        )));
        if ($ids === [] || \count($ids) > 500) {
            throw $this->createNotFoundException('No day shifts selected');
        }

        $shifts = $this->repository->findBy(['id' => $ids], ['fromDate' => 'ASC']);
        if (\count($shifts) !== \count($ids)) {
            throw $this->createNotFoundException('Day shift not found');
        }

        return array_values($shifts);
    }

    /**
     * Checks every selected shift before the first one is changed.
     *
     * @param iterable<WorkdayShift> $shifts
     */
    private function assertCanApproveAll(iterable $shifts): void
    {
        foreach ($shifts as $shift) {
            if (!$this->permissions->canApprove($shift)) {
                throw $this->createAccessDeniedException();
            }
        }
    }

    private function assertCsrf(Request $request): void
    {
        if (!$this->isCsrfTokenValid(self::CSRF_ACTION, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token');
        }
    }

    private function prefillDate(Request $request): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $request->query->get('from'));

        return $date !== false ? $date : new \DateTimeImmutable('today');
    }

    private function addFormError(FormInterface $field, ?string $key): void
    {
        $field->addError(new FormError($this->translator->trans($key ?? 'holiday.error.invalid_date', [], 'flashmessages')));
    }
}
