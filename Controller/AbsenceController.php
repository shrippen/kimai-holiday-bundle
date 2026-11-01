<?php

namespace KimaiPlugin\HolidayBundle\Controller;

use App\Controller\AbstractController;
use App\Entity\User;
use App\Form\Type\UserType;
use App\Repository\Query\BaseQuery;
use App\Repository\UserRepository;
use App\Utils\DataTable;
use App\Utils\PageSetup;
use App\Utils\Pagination;
use KimaiPlugin\HolidayBundle\Entity\Absence;
use KimaiPlugin\HolidayBundle\Enum\AbsenceStatus;
use KimaiPlugin\HolidayBundle\Enum\AbsenceType;
use KimaiPlugin\HolidayBundle\Form\AbsenceTypeForm;
use KimaiPlugin\HolidayBundle\Repository\AbsenceRepository;
use KimaiPlugin\HolidayBundle\Repository\WorkdayShiftRepository;
use KimaiPlugin\HolidayBundle\Service\AbsenceApprovalService;
use KimaiPlugin\HolidayBundle\Service\AbsenceExcelExporter;
use KimaiPlugin\HolidayBundle\Service\AbsencePermissions;
use KimaiPlugin\HolidayBundle\Service\AbsenceWorkdayHelper;
use KimaiPlugin\HolidayBundle\Service\ApproverResolver;
use KimaiPlugin\HolidayBundle\Service\UserIcsTokenService;
use KimaiPlugin\HolidayBundle\Service\VacationAccountService;
use KimaiPlugin\HolidayBundle\Service\VacationYearResolver;
use KimaiPlugin\HolidayBundle\Service\WorkdayShiftService;
use KimaiPlugin\HolidayBundle\Service\WorkingTimeCalculator;
use Pagerfanta\Adapter\ArrayAdapter;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route(path: '/holiday')]
class AbsenceController extends AbstractController
{
    use TargetUserTrait;
    use HolidayUiTrait;

    /**
     * data-form-event of the plugin's modal forms: kit.js reloads the page on it, so kpu_result callouts and
     * KPI tiles are rendered fresh (kimai-plugin-ui GUIDELINES 3.6, "keep URL" variant of formSuccess()).
     */
    public const UPDATE_EVENT = 'kpu.reload';
    public const CSRF_ACTION = 'holiday_absence_action';
    /** Undo window of approve/reject (GUIDELINES 3.5): 15 minutes, same user, same session. */
    private const UNDO_WINDOW = 900;
    private const UNDO_SESSION_PREFIX = 'holiday.undo.';

    public function __construct(
        private readonly AbsenceRepository $absenceRepository,
        private readonly AbsenceApprovalService $approvalService,
        private readonly WorkingTimeCalculator $calculator,
        private readonly AbsenceExcelExporter $excelExporter,
        private readonly UserRepository $userRepository,
        private readonly UserIcsTokenService $icsTokenService,
        private readonly AbsenceWorkdayHelper $workdayHelper,
        private readonly AbsencePermissions $permissions,
        private readonly TranslatorInterface $translator,
        private readonly VacationYearResolver $yearResolver,
        private readonly VacationAccountService $accountService,
        private readonly ApproverResolver $approverResolver,
        private readonly WorkdayShiftRepository $shiftRepository,
    ) {
    }

    #[Route(path: '/absence/{year}', name: 'holiday_absence', defaults: ['year' => null], methods: ['GET'], requirements: ['year' => '\d{1,4}'])]
    #[IsGranted('absence')]
    public function index(Request $request, ?int $year = null): Response
    {
        $user = $this->getTargetUser($request, $this->userRepository);
        $vacationYear = $this->yearResolver->fromRouteParam($user, $year);
        $year = $vacationYear->index;
        $absences = $this->absenceRepository->findByUserBetween($user, $vacationYear->start, $vacationYear->end);
        $account = $this->accountService->calculate($user, $vacationYear);

        $absenceDays = [];
        $requestedVacation = 0.0;
        $sickDays = 0.0;
        $hasRequested = false;
        foreach ($absences as $a) {
            if ($a->getId() === null) {
                continue;
            }
            $days = $this->workdayHelper->countDaysBetween($a, $vacationYear->start, $vacationYear->end);
            $absenceDays[$a->getId()] = $days;
            if ($a->getStatus() === AbsenceStatus::REQUESTED) {
                $hasRequested = true;
                if ($a->getType() === AbsenceType::VACATION) {
                    $requestedVacation += $days;
                }
            }
            if ($a->getStatus() === AbsenceStatus::APPROVED && $a->getType() === AbsenceType::SICKNESS) {
                $sickDays += $days;
            }
        }

        // The owner gets the ICS token on first view; admins only see an existing one.
        if ($user === $this->getUser() && $this->permissions->canManageIcs($user)) {
            $this->icsTokenService->getOrCreateToken($user);
        }

        $canApprove = $this->permissions->canApproveFor($user);
        $canCreate = $this->permissions->canEditFor($user);

        $table = new DataTable('holiday_absences', new BaseQuery());
        $table->setPagination(new Pagination(new ArrayAdapter($absences)));
        $table->setSticky(false);
        if ($canApprove && $hasRequested) {
            $table->addColumn('select', [
                'class' => 'alwaysVisible multiCheckbox w-min',
                'orderBy' => false,
                'title' => false,
                'html_after' => sprintf(
                    '<input type="checkbox" class="form-check-input m-0 align-middle kpu-select-all" data-kpu-form="holiday-absence-bulk" aria-label="%1$s" title="%1$s">',
                    htmlspecialchars($this->translator->trans('kpu.bulk.select_all', [], 'kpu'))
                ),
            ]);
        }
        $table->addColumn('type', ['class' => 'd-none d-md-table-cell', 'orderBy' => false, 'title' => 'holiday.absence.type']);
        $table->addColumn('period', ['class' => 'alwaysVisible', 'orderBy' => false, 'title' => 'holiday.absence.period']);
        $table->addColumn('days', ['class' => 'text-end w-min', 'orderBy' => false, 'title' => 'holiday.absence.days']);
        $table->addColumn('half_day', ['class' => 'd-none d-lg-table-cell text-center w-min', 'orderBy' => false, 'title' => 'holiday.absence.half_day']);
        $table->addColumn('comment', ['class' => 'd-none d-xl-table-cell', 'orderBy' => false, 'title' => 'comment']);
        $table->addColumn('status', ['class' => 'w-min', 'orderBy' => false, 'title' => 'status']);
        $table->addColumn('actions', ['class' => 'actions alwaysVisible']);

        $page = new PageSetup($this->translator->trans('holiday.page.absence', ['%year%' => $vacationYear->label()]));
        $page->setActionName('holiday_absences');
        $page->setActionPayload([
            'user' => $user,
            'year' => $year,
            'calendar_year' => (int) $vacationYear->start->format('Y'),
            'can_create' => $canCreate,
            'can_ics' => $this->permissions->canManageIcs($user),
        ]);
        $page->setHelp($this->helpUrl('absences'));
        $page->setDataTable($table);

        $userForm = null;
        if ($this->isGranted('hours_other_profile') || $this->isGranted('view_other_absence') || $this->isGranted('edit_other_absence')) {
            $userForm = $this->createFormForGetRequest(FormType::class, ['user' => $user], [
                'action' => $this->generateUrl('holiday_absence'),
                'csrf_protection' => false,
            ]);
            $userForm->add('user', UserType::class, ['label' => false, 'required' => true]);
        }

        $current = $this->yearResolver->forDate($user, new \DateTimeImmutable('today'));
        $userParam = $user === $this->getUser() ? null : $user->getId();
        $previous = $this->yearResolver->previous($user, $vacationYear);

        return $this->render('@Holiday/absence/index.html.twig', [
            'page_setup' => $page,
            'dataTable' => $table,
            'year' => $year,
            'vacation_year' => $vacationYear,
            'no_start_date' => $this->yearResolver->isCalendarFallback($user),
            'target_user' => $user,
            'user_param' => $userParam,
            'user_form' => $userForm?->createView(),
            'absence_days' => $absenceDays,
            'can_create' => $canCreate,
            'bulk_enabled' => $canApprove && $hasRequested,
            'kpi' => [
                'taken' => $account->used,
                'requested' => $requestedVacation,
                'sick' => $sickDays,
                'left' => $account->balance,
                'entitlement' => $account->entitlement + $account->carryOver + $account->corrections,
                'carry_over' => $account->carryOver,
            ],
            'period' => [
                'prev' => $previous === null ? null : $this->generateUrl('holiday_absence', ['year' => $previous->index, 'user' => $userParam]),
                'next' => $this->generateUrl('holiday_absence', ['year' => $this->yearResolver->next($user, $vacationYear)->index, 'user' => $userParam]),
                'today' => $vacationYear->index === $current->index ? null : $this->generateUrl('holiday_absence', ['year' => $current->index, 'user' => $userParam]),
            ],
        ]);
    }

    /**
     * Open requests of all users the current user may approve for (second channel next to the mails).
     */
    #[Route(path: '/absence/requests', name: 'holiday_absence_requests', methods: ['GET'])]
    public function requests(): Response
    {
        if (!$this->isGranted('approve_other_absence') && !$this->isGranted(WorkdayShiftService::PERMISSION)) {
            throw $this->createAccessDeniedException();
        }

        $absences = $this->absenceRepository->findRequestedForUsers($this->approverResolver->subjectsFor($this->getUser()));

        $days = [];
        foreach ($absences as $absence) {
            $days[$absence->getId()] = $this->workdayHelper->countDaysBetween($absence, $absence->getStartDate(), $absence->getEndDate());
        }

        $table = new DataTable('holiday_requests', new BaseQuery());
        $table->setPagination(new Pagination(new ArrayAdapter($absences)));
        $table->setSticky(false);
        $table->addColumn('user', ['class' => 'alwaysVisible', 'orderBy' => false, 'title' => 'holiday.requests.user']);
        $table->addColumn('type', ['class' => 'd-none d-md-table-cell', 'orderBy' => false, 'title' => 'holiday.absence.type']);
        $table->addColumn('period', ['class' => 'alwaysVisible', 'orderBy' => false, 'title' => 'holiday.absence.period']);
        $table->addColumn('days', ['class' => 'text-end w-min', 'orderBy' => false, 'title' => 'holiday.absence.days']);
        $table->addColumn('comment', ['class' => 'd-none d-xl-table-cell', 'orderBy' => false, 'title' => 'comment']);
        $table->addColumn('actions', ['class' => 'actions alwaysVisible']);

        $shifts = $this->shiftRepository->findRequestedForUsers($this->approverResolver->subjectsFor($this->getUser(), WorkdayShiftService::PERMISSION));
        $shiftTable = new DataTable('holiday_shift_requests', new BaseQuery());
        $shiftTable->setPagination(new Pagination(new ArrayAdapter($shifts)));
        $shiftTable->setSticky(false);
        $shiftTable->addColumn('user', ['class' => 'alwaysVisible', 'orderBy' => false, 'title' => 'holiday.requests.user']);
        $shiftTable->addColumn('change', ['class' => 'alwaysVisible', 'orderBy' => false, 'title' => 'holiday.shift.requests.change']);
        $shiftTable->addColumn('comment', ['class' => 'd-none d-xl-table-cell', 'orderBy' => false, 'title' => 'comment']);
        $shiftTable->addColumn('actions', ['class' => 'actions alwaysVisible']);

        $page = new PageSetup($this->translator->trans('holiday.requests.title'));
        $page->setActionName('holiday_absences');
        $page->setActionPayload(['user' => $this->getUser(), 'can_create' => false, 'can_ics' => false]);
        $page->setDataTable($table);

        return $this->render('@Holiday/absence/requests.html.twig', [
            'page_setup' => $page,
            'dataTable' => $table,
            'absence_days' => $days,
            'shiftTable' => $shiftTable,
        ]);
    }

    #[Route(path: '/absence/create', name: 'holiday_absence_create', methods: ['GET', 'POST'])]
    #[IsGranted('absence')]
    public function create(Request $request): Response
    {
        $user = $this->getTargetUser($request, $this->userRepository);
        if (!$this->permissions->canEditFor($user)) {
            throw $this->createAccessDeniedException();
        }

        $absence = new Absence();
        $absence->setUser($user);
        $absence->setStartDate(new \DateTimeImmutable('today'));
        $absence->setEndDate(new \DateTimeImmutable('today'));

        $userParam = $user === $this->getUser() ? null : $user->getId();
        $form = $this->createAbsenceForm($absence, $this->generateUrl('holiday_absence_create', ['user' => $userParam]));
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->approvalService->create($absence, $this->getUser());

                // show the year of the new absence
                return $this->formSuccess($request, 'holiday_absence', [
                    'year' => $this->yearIndexOf($user, $absence->getStartDate()),
                    'user' => $userParam,
                ], false);
            } catch (\InvalidArgumentException|\RuntimeException $e) {
                $this->addServiceError($form, $e);
            }
        }

        return $this->renderAbsenceForm($form, $absence, $this->yearIndexOf($user, new \DateTimeImmutable('today')));
    }

    #[Route(path: '/absence/{id}/edit', name: 'holiday_absence_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Absence $absence): Response
    {
        $this->assertCanEdit($absence);

        $previousStart = $absence->getStartDate();
        $previousEnd = $absence->getEndDate();
        $wasApproved = $absence->getStatus() === AbsenceStatus::APPROVED;

        $form = $this->createAbsenceForm($absence, $this->generateUrl('holiday_absence_edit', ['id' => $absence->getId()]));
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->approvalService->update($absence, $this->getUser(), $previousStart, $previousEnd);
                if ($wasApproved && $absence->getStatus() === AbsenceStatus::REQUESTED) {
                    $this->addFlash('kpu_result', $this->translator->trans('holiday.absence.edit.reapproval', [], 'flashmessages'));
                }

                return $this->formSuccess($request, 'holiday_absence', $this->listParameters($absence));
            } catch (\InvalidArgumentException|\RuntimeException $e) {
                $this->addServiceError($form, $e);
            }
        }

        return $this->renderAbsenceForm($form, $absence, $this->yearIndexOf($absence->getUser(), $previousStart));
    }

    #[Route(path: '/absence/ics', name: 'holiday_absence_ics', methods: ['GET'])]
    #[IsGranted('absence')]
    public function ics(Request $request): Response
    {
        $user = $this->getTargetUser($request, $this->userRepository);
        if (!$this->permissions->canManageIcs($user)) {
            throw $this->createAccessDeniedException();
        }

        $token = $user === $this->getUser()
            ? $this->icsTokenService->getOrCreateToken($user)
            : $this->icsTokenService->getToken($user);
        $year = $this->validYear($request);
        $userParam = $user === $this->getUser() ? null : $user->getId();

        $form = $this->createPlainForm(
            'holiday_ics_regenerate',
            $this->generateUrl('holiday_absence_ics_regenerate', ['user' => $userParam, 'year' => $year])
        );

        $page = new PageSetup('holiday.absence.ics.title');
        $page->setHelp($this->helpUrl('personal-calendar-ics'));

        return $this->render('@Holiday/absence/ics.html.twig', [
            'page_setup' => $page,
            'form' => $form->createView(),
            'ics_url' => $token !== null ? $this->generateUrl('holiday_user_ics', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL) : null,
            'target_user' => $user,
            'back' => $this->generateUrl('holiday_absence', ['year' => $year, 'user' => $userParam]),
        ]);
    }

    #[Route(path: '/absence/ics/regenerate', name: 'holiday_absence_ics_regenerate', methods: ['POST'])]
    #[IsGranted('absence')]
    public function regenerateIcs(Request $request): Response
    {
        $this->assertCsrf($request, 'holiday_ics_regenerate');
        $user = $this->getTargetUser($request, $this->userRepository);
        if (!$this->permissions->canManageIcs($user)) {
            throw $this->createAccessDeniedException();
        }

        $this->icsTokenService->regenerateToken($user);
        $this->addFlash('kpu_result', $this->translator->trans('holiday.absence.ics.regenerated', [], 'flashmessages'));

        return $this->formSuccess($request, 'holiday_absence', [
            'year' => $this->validYear($request),
            'user' => $user === $this->getUser() ? null : $user->getId(),
        ]);
    }

    /**
     * Approve the selected requested absences (kit bulk action, reversible via holiday_absence_reopen).
     */
    #[Route(path: '/absence/approve', name: 'holiday_absence_bulk_approve', methods: ['POST'])]
    #[IsGranted('absence')]
    public function bulkApprove(Request $request): Response
    {
        return $this->bulkDecision($request, true);
    }

    #[Route(path: '/absence/reject', name: 'holiday_absence_bulk_reject', methods: ['POST'])]
    #[IsGranted('absence')]
    public function bulkReject(Request $request): Response
    {
        return $this->bulkDecision($request, false);
    }

    /**
     * Undo of the current user's own approve/reject action (kimai-plugin-ui GUIDELINES 3.5 "Rückgängig-Fenster"):
     * only the action stored in this session by bulkDecision(), only by the same user, only within UNDO_WINDOW
     * seconds, only its absences and only if they were not changed since. The normal approval permission is
     * still checked (no exception granted for this plugin).
     */
    #[Route(path: '/absence/reopen/{action}', name: 'holiday_absence_reopen', methods: ['POST'], requirements: ['action' => '[a-f0-9]{16}'])]
    #[IsGranted('absence')]
    public function reopen(Request $request, string $action): Response
    {
        $this->assertCsrf($request, self::CSRF_ACTION);

        $session = $request->getSession();
        $key = self::UNDO_SESSION_PREFIX . $action;
        $entry = $session->get($key);
        $session->remove($key);
        $user = $this->getUser();

        if (!\is_array($entry) || ($entry['user'] ?? null) !== $user?->getId() || (int) ($entry['at'] ?? 0) < time() - self::UNDO_WINDOW) {
            return $this->actionResult($request, $this->translator->trans('holiday.absence.undo.expired'), null, 'holiday_absence', $this->listParameters(null), 409);
        }

        /** @var array<int, array{status: string, approved_at: string}> $before */
        $before = $entry['absences'];
        $requested = array_map('intval', $request->request->all('ids'));
        if (array_diff($requested, array_keys($before)) !== []) {
            throw $this->createAccessDeniedException('Undo is limited to the absences of the action');
        }

        $absences = $this->absenceRepository->findBy(['id' => array_keys($before)], ['startDate' => 'ASC']);
        $this->assertCanApproveAll($absences);
        $done = 0;
        $changed = 0;
        foreach ($absences as $absence) {
            $state = $before[(int) $absence->getId()];
            // changed since the action (someone else decided, edited or reopened it): leave it alone
            if ($absence->getStatus()->value !== $state['status']
                || $absence->getApprovedBy()?->getId() !== $user?->getId()
                || $absence->getApprovedAt()?->format('Y-m-d H:i:s') !== $state['approved_at']) {
                ++$changed;
                continue;
            }
            try {
                $this->approvalService->request($absence, true);
                ++$done;
            } catch (\InvalidArgumentException|\RuntimeException) {
                ++$changed; // locked month: leave unchanged
            }
        }
        $changed += \count($before) - \count($absences); // deleted in the meantime

        $parameters = $this->listParameters($absences[0] ?? null);
        if ($done === 0) {
            return $this->actionResult($request, $this->translator->trans('holiday.absence.undo.changed'), null, 'holiday_absence', $parameters, 409);
        }

        $message = $this->translator->trans('holiday.absence.bulk.reopened', ['%count%' => $done]);
        if ($changed > 0) {
            $message .= ' ' . $this->translator->trans('holiday.absence.undo.skipped', ['%count%' => $changed]);
        }

        return $this->actionResult($request, $message, null, 'holiday_absence', $parameters);
    }

    /** @deprecated single-row form of holiday_absence_bulk_approve, kept for existing links */
    #[Route(path: '/absence/{id}/approve', name: 'holiday_absence_approve', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function approve(Request $request, Absence $absence): Response
    {
        $request->request->set('ids', [$absence->getId()]);

        return $this->bulkDecision($request, true);
    }

    /** @deprecated single-row form of holiday_absence_bulk_reject, kept for existing links */
    #[Route(path: '/absence/{id}/reject', name: 'holiday_absence_reject', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function reject(Request $request, Absence $absence): Response
    {
        $request->request->set('ids', [$absence->getId()]);

        return $this->bulkDecision($request, false);
    }

    #[Route(path: '/absence/{id}/delete', name: 'holiday_absence_delete', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Absence $absence): Response
    {
        if (!$this->permissions->canDelete($absence)) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createPlainForm(self::CSRF_ACTION, $this->generateUrl('holiday_absence_delete', ['id' => $absence->getId()]));
        $parameters = $this->listParameters($absence);

        if ($request->isMethod('POST')) {
            $this->assertCsrf($request, self::CSRF_ACTION);
            try {
                $this->approvalService->delete($absence);
                $this->flashSuccess('action.delete.success');

                return $this->formSuccess($request, 'holiday_absence', $parameters);
            } catch (\InvalidArgumentException|\RuntimeException $e) {
                $this->addServiceError($form, $e);
            }
        }

        return $this->render('@Holiday/absence/delete.html.twig', [
            'page_setup' => $this->createFormPage('holiday.absence.delete'),
            'absence' => $absence,
            'form' => $form->createView(),
            'back' => $this->generateUrl('holiday_absence', $parameters),
        ]);
    }

    #[Route(path: '/absence/{year}/export', name: 'holiday_absence_export', methods: ['GET'], requirements: ['year' => '\d{1,4}'])]
    #[IsGranted('absence')]
    public function export(Request $request, int $year): Response
    {
        $user = $this->getTargetUser($request, $this->userRepository);
        $vacationYear = $this->yearResolver->fromRouteParam($user, $year);
        $absences = $this->absenceRepository->findByUserBetween($user, $vacationYear->start, $vacationYear->end);
        $csv = $this->excelExporter->exportCsv($user, $absences);

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => sprintf('attachment; filename="absences-%s.csv"', $vacationYear->start->format('Y-m-d')),
        ]);
    }

    private function bulkDecision(Request $request, bool $approve): Response
    {
        $this->assertCsrf($request, self::CSRF_ACTION);
        $absences = $this->loadSelected($request);

        $this->assertCanApproveAll($absences);

        $doneIds = [];
        $doneAbsences = [];
        $skipped = 0;
        $firstError = null;
        foreach ($absences as $absence) {
            if ($absence->getStatus() !== AbsenceStatus::REQUESTED) {
                ++$skipped;
                continue;
            }
            try {
                $approve
                    ? $this->approvalService->approve($absence, $this->getUser())
                    : $this->approvalService->reject($absence, $this->getUser());
                $doneIds[] = (int) $absence->getId();
                $doneAbsences[] = $absence;
            } catch (\InvalidArgumentException|\RuntimeException $e) {
                $firstError ??= $this->errorKey($e);
                ++$skipped;
            }
        }

        $parameters = $this->listParameters($absences[0] ?? null);
        if ($doneIds === []) {
            $reason = $firstError === null ? 'holiday.absence.bulk.none' : $firstError;

            return $this->actionResult($request, $this->translator->trans($reason, [], $firstError === null ? null : 'flashmessages'), null, 'holiday_absence', $parameters, 422);
        }

        $message = $this->translator->trans($approve ? 'holiday.absence.bulk.approved' : 'holiday.absence.bulk.rejected', ['%count%' => \count($doneIds)]);
        if ($skipped > 0) {
            $message .= ' ' . $this->translator->trans('holiday.absence.bulk.skipped', ['%count%' => $skipped]);
        }

        $undo = [
            'url' => $this->generateUrl('holiday_absence_reopen', ['action' => $this->rememberUndo($request, $doneAbsences)]),
            'token' => $this->container->get('security.csrf.token_manager')->getToken(self::CSRF_ACTION)->getValue(),
            'ids' => $doneIds,
        ];

        return $this->actionResult($request, $message, $undo, 'holiday_absence', $parameters);
    }

    /**
     * Stores the undo entry of an approve/reject action in the session (GUIDELINES 3.5) and returns its id.
     * Expired entries of earlier actions are dropped on the way.
     *
     * @param list<Absence> $absences
     */
    private function rememberUndo(Request $request, array $absences): string
    {
        $session = $request->getSession();
        foreach (array_keys($session->all()) as $name) {
            if (str_starts_with($name, self::UNDO_SESSION_PREFIX) && (int) ($session->get($name)['at'] ?? 0) < time() - self::UNDO_WINDOW) {
                $session->remove($name);
            }
        }

        $state = [];
        foreach ($absences as $absence) {
            $state[(int) $absence->getId()] = [
                'status' => $absence->getStatus()->value,
                'approved_at' => $absence->getApprovedAt()?->format('Y-m-d H:i:s'),
            ];
        }

        $action = bin2hex(random_bytes(8));
        $session->set(self::UNDO_SESSION_PREFIX . $action, ['user' => $this->getUser()?->getId(), 'absences' => $state, 'at' => time()]);

        return $action;
    }

    /**
     * @return list<Absence>
     */
    private function loadSelected(Request $request): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $request->request->all('ids')),
            static fn (int $id): bool => $id > 0
        )));
        if ($ids === [] || \count($ids) > 500) {
            throw $this->createNotFoundException('No absences selected');
        }

        $absences = $this->absenceRepository->findBy(['id' => $ids], ['startDate' => 'ASC']);
        if (\count($absences) !== \count($ids)) {
            throw $this->createNotFoundException('Absence not found');
        }

        return array_values($absences);
    }

    /**
     * @return array{year: int, user: int|null}
     */
    private function listParameters(?Absence $absence): array
    {
        $user = $absence?->getUser();

        return [
            'year' => $user === null ? (int) date('Y') : $this->yearIndexOf($user, $absence?->getStartDate()),
            'user' => $user === null || $user === $this->getUser() ? null : $user->getId(),
        ];
    }

    /**
     * Vacation-year index (calendar year without a start date) of the year containing $date.
     */
    private function yearIndexOf(?User $user, ?\DateTimeInterface $date): int
    {
        $date ??= new \DateTimeImmutable('today');
        if ($user === null) {
            return (int) $date->format('Y');
        }

        return $this->yearResolver->forDate($user, $date)->index;
    }

    private function createAbsenceForm(Absence $absence, string $action): FormInterface
    {
        return $this->createForm(AbsenceTypeForm::class, $absence, [
            'action' => $action,
            'method' => 'POST',
            'attr' => ['data-form-event' => self::UPDATE_EVENT],
        ]);
    }

    private function renderAbsenceForm(FormInterface $form, Absence $absence, int $year): Response
    {
        $user = $absence->getUser();

        return $this->render('@Holiday/absence/edit.html.twig', [
            'page_setup' => $this->createFormPage($absence->getId() === null ? 'holiday.absence.create' : 'holiday.absence.edit'),
            'absence' => $absence,
            'form' => $form->createView(),
            'target_user' => $user,
            'back' => $this->generateUrl('holiday_absence', [
                'year' => $year > 0 ? $year : (int) date('Y'),
                'user' => $user === null || $user === $this->getUser() ? null : $user->getId(),
            ]),
        ]);
    }

    private function createFormPage(string $title): PageSetup
    {
        $page = new PageSetup($title);
        $page->setHelp($this->helpUrl('absences'));

        return $page;
    }

    /**
     * Form without fields whose CSRF field is the plain "_token" (same token ids as before the modal UI),
     * used for confirmation modals.
     */
    private function createPlainForm(string $tokenId, string $action): FormInterface
    {
        return $this->container->get('form.factory')->createNamed('', FormType::class, null, [
            'action' => $action,
            'method' => 'POST',
            'csrf_field_name' => '_token',
            'csrf_token_id' => $tokenId,
            'attr' => ['data-form-event' => self::UPDATE_EVENT],
        ]);
    }

    private function addServiceError(FormInterface $form, \Throwable $e): void
    {
        $key = $this->errorKey($e);
        if ($key === null) {
            throw $e;
        }

        $field = match ($key) {
            'holiday.error.end_before_start', 'holiday.error.range_too_long' => 'endDate',
            'holiday.error.invalid_duration' => 'duration',
            'holiday.error.comment_required' => 'comment',
            default => null,
        };
        $error = new FormError($this->translator->trans($key, [], 'flashmessages'));
        if ($field !== null && $form->has($field)) {
            $form->get($field)->addError($error);
        } else {
            $form->addError($error);
        }
    }

    private function validYear(Request $request): int
    {
        $year = $request->query->getInt('year');

        return $year >= 1000 && $year <= 9999 ? $year : (int) date('Y');
    }

    private function assertCsrf(Request $request, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token');
        }
    }

    /**
     * Checks every selected absence before the first one is changed, so a selection with one foreign absence
     * changes nothing.
     *
     * @param iterable<Absence> $absences
     */
    private function assertCanApproveAll(iterable $absences): void
    {
        foreach ($absences as $absence) {
            if (!$this->permissions->canApprove($absence)) {
                throw $this->createAccessDeniedException();
            }
        }
    }

    private function assertCanEdit(Absence $absence): void
    {
        if (!$this->permissions->canEdit($absence)) {
            throw $this->createAccessDeniedException();
        }
    }
}
