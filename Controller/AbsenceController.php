<?php

namespace KimaiPlugin\HolidayBundle\Controller;

use App\Controller\AbstractController;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Utils\PageSetup;
use KimaiPlugin\HolidayBundle\Entity\Absence;
use KimaiPlugin\HolidayBundle\Form\AbsenceTypeForm;
use KimaiPlugin\HolidayBundle\Repository\AbsenceRepository;
use KimaiPlugin\HolidayBundle\Service\AbsenceApprovalService;
use KimaiPlugin\HolidayBundle\Service\AbsenceExcelExporter;
use KimaiPlugin\HolidayBundle\Service\AbsenceWorkdayHelper;
use KimaiPlugin\HolidayBundle\Service\UserIcsTokenService;
use KimaiPlugin\HolidayBundle\Service\WorkingTimeCalculator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/holiday')]
class AbsenceController extends AbstractController
{
    use TargetUserTrait;

    public function __construct(
        private readonly AbsenceRepository $absenceRepository,
        private readonly AbsenceApprovalService $approvalService,
        private readonly WorkingTimeCalculator $calculator,
        private readonly AbsenceExcelExporter $excelExporter,
        private readonly UserRepository $userRepository,
        private readonly UserIcsTokenService $icsTokenService,
        private readonly AbsenceWorkdayHelper $workdayHelper,
    ) {
    }

    #[Route(path: '/absence/{year}', name: 'holiday_absence', defaults: ['year' => null], methods: ['GET', 'POST'], requirements: ['year' => '\d{4}'])]
    #[IsGranted('absence')]
    public function index(Request $request, ?int $year = null): Response
    {
        $year ??= (int) date('Y');
        $user = $this->getTargetUser($request, $this->userRepository);
        $absences = $this->absenceRepository->findByUserAndYear($user, $year);
        $vacationUsed = $this->calculator->calculateVacationDaysUsed($user, $year);
        $yearData = $this->calculator->calculateYear($user, $year);

        $absence = new Absence();
        $absence->setUser($user);
        $absence->setStartDate(new \DateTimeImmutable('today'));
        $absence->setEndDate(new \DateTimeImmutable('today'));

        $form = $this->createForm(AbsenceTypeForm::class, $absence);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->assertCanEdit($absence);

            try {
                $this->approvalService->create($absence, $this->getUser());
                $this->flashSuccess('action.update.success');
            } catch (\Throwable $e) {
                $this->flashError($e->getMessage());
            }

            return $this->redirectToRoute('holiday_absence', ['year' => $year, 'user' => $user->getId()]);
        }

        $page = new PageSetup('menu.absence');

        $icsUrl = null;
        $icsManage = $this->canManageIcs($user);
        if ($icsManage) {
            // Only the owner gets a token created on first view; admins see an existing one.
            $token = $user === $this->getUser()
                ? $this->icsTokenService->getOrCreateToken($user)
                : $this->icsTokenService->getToken($user);
            if ($token !== null) {
                $icsUrl = $this->generateUrl('holiday_user_ics', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);
            }
        }

        $absenceDays = [];
        foreach ($absences as $a) {
            if ($a->getId() !== null) {
                $absenceDays[$a->getId()] = $this->workdayHelper->countDays($a, $year);
            }
        }

        return $this->render('@Holiday/absence/index.html.twig', [
            'page_setup' => $page,
            'year' => $year,
            'target_user' => $user,
            'absences' => $absences,
            'absence_days' => $absenceDays,
            'form' => $form->createView(),
            'vacationUsed' => $vacationUsed,
            'vacationBalance' => $yearData['vacationBalance'],
            'vacationEntitlement' => $yearData['vacationEntitlement'],
            'ics_url' => $icsUrl,
            'ics_manage' => $icsManage,
        ]);
    }

    #[Route(path: '/absence/{id}/edit', name: 'holiday_absence_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Absence $absence): Response
    {
        $this->assertCanEdit($absence);

        $previousStart = $absence->getStartDate();
        $previousEnd = $absence->getEndDate();

        $form = $this->createForm(AbsenceTypeForm::class, $absence);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->approvalService->update($absence, $this->getUser(), $previousStart, $previousEnd);
                $this->flashSuccess('absence.edit.reapproval');
            } catch (\Throwable $e) {
                $this->flashError($e->getMessage());
            }

            return $this->redirectToRoute('holiday_absence', [
                'year' => (int) $absence->getStartDate()?->format('Y'),
                'user' => $absence->getUser()?->getId(),
            ]);
        }

        $page = new PageSetup('absence.edit');

        return $this->render('@Holiday/absence/edit.html.twig', [
            'page_setup' => $page,
            'absence' => $absence,
            'form' => $form->createView(),
            'target_user' => $absence->getUser(),
            'year' => (int) $absence->getStartDate()?->format('Y'),
        ]);
    }

    #[Route(path: '/absence/ics/regenerate', name: 'holiday_absence_ics_regenerate', methods: ['POST'])]
    #[IsGranted('absence')]
    public function regenerateIcs(Request $request): Response
    {
        $this->assertCsrf($request, 'holiday_ics_regenerate');
        $user = $this->getTargetUser($request, $this->userRepository);
        if (!$this->canManageIcs($user)) {
            throw $this->createAccessDeniedException();
        }

        $this->icsTokenService->regenerateToken($user);
        $this->flashSuccess('absence.ics.regenerated');

        return $this->redirectToRoute('holiday_absence', [
            'year' => ($y = $request->query->getInt('year')) >= 1000 && $y <= 9999 ? $y : (int) date('Y'),
            'user' => $user->getId(),
        ]);
    }

    #[Route(path: '/absence/{id}/approve', name: 'holiday_absence_approve', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function approve(Request $request, Absence $absence): Response
    {
        $this->assertCsrf($request, 'holiday_absence_action');
        $this->assertCanApprove($absence);
        try {
            $this->approvalService->approve($absence, $this->getUser());
            $this->flashSuccess('action.update.success');
        } catch (\Throwable $e) {
            $this->flashError($e->getMessage());
        }

        return $this->redirectToRoute('holiday_absence', [
            'year' => (int) $absence->getStartDate()?->format('Y'),
            'user' => $absence->getUser()?->getId(),
        ]);
    }

    #[Route(path: '/absence/{id}/reject', name: 'holiday_absence_reject', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function reject(Request $request, Absence $absence): Response
    {
        $this->assertCsrf($request, 'holiday_absence_action');
        $this->assertCanApprove($absence);
        try {
            $this->approvalService->reject($absence, $this->getUser());
            $this->flashSuccess('action.update.success');
        } catch (\Throwable $e) {
            $this->flashError($e->getMessage());
        }

        return $this->redirectToRoute('holiday_absence', [
            'year' => (int) $absence->getStartDate()?->format('Y'),
            'user' => $absence->getUser()?->getId(),
        ]);
    }

    #[Route(path: '/absence/{id}/delete', name: 'holiday_absence_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Absence $absence): Response
    {
        $this->assertCsrf($request, 'holiday_absence_action');
        $user = $absence->getUser();
        $own = $user === $this->getUser();
        if ($own && !$this->isGranted('delete_own_absence')) {
            throw $this->createAccessDeniedException();
        }
        if (!$own && (!$this->isGranted('delete_other_absence') || !$this->canAccessUser($user))) {
            throw $this->createAccessDeniedException();
        }

        $year = (int) $absence->getStartDate()?->format('Y');
        $userId = $user?->getId();

        try {
            $this->approvalService->delete($absence);
            $this->flashSuccess('action.delete.success');
        } catch (\Throwable $e) {
            $this->flashError($e->getMessage());
        }

        return $this->redirectToRoute('holiday_absence', ['year' => $year, 'user' => $userId]);
    }

    #[Route(path: '/absence/{year}/export', name: 'holiday_absence_export', methods: ['GET'], requirements: ['year' => '\d{4}'])]
    #[IsGranted('absence')]
    public function export(Request $request, int $year): Response
    {
        $user = $this->getTargetUser($request, $this->userRepository);
        $absences = $this->absenceRepository->findByUserAndYear($user, $year);
        $csv = $this->excelExporter->exportCsv($user, $absences);

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => sprintf('attachment; filename="absences-%d.csv"', $year),
        ]);
    }

    private function assertCsrf(Request $request, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token');
        }
    }

    private function assertCanEdit(Absence $absence): void
    {
        $own = $absence->getUser() === $this->getUser();
        if ($own && $this->isGranted('edit_own_absence')) {
            return;
        }
        if (!$own && $this->isGranted('edit_other_absence') && $this->canAccessUser($absence->getUser())) {
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
     * The ICS token is a secret of its owner: only the owner and admins (view_all_data + edit_other_absence)
     * may see or regenerate it. Team leads do not.
     */
    private function canManageIcs(User $user): bool
    {
        /** @var User $current */
        $current = $this->getUser();
        if ($user === $current) {
            return $this->isGranted('absence');
        }

        return $current->canSeeAllData() && $this->isGranted('edit_other_absence');
    }
}
