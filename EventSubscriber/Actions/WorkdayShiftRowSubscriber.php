<?php

namespace KimaiPlugin\HolidayBundle\EventSubscriber\Actions;

use App\Event\PageActionsEvent;
use App\EventSubscriber\Actions\AbstractActionsSubscriber;
use KimaiPlugin\HolidayBundle\Controller\WorkdayShiftController;
use KimaiPlugin\HolidayBundle\Entity\WorkdayShift;
use KimaiPlugin\HolidayBundle\Enum\AbsenceStatus;
use KimaiPlugin\HolidayBundle\Service\WorkdayShiftPermissions;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Row menu ("…") of the day shift list on the open requests page.
 */
final class WorkdayShiftRowSubscriber extends AbstractActionsSubscriber
{
    public function __construct(
        AuthorizationCheckerInterface $auth,
        UrlGeneratorInterface $urlGenerator,
        private readonly WorkdayShiftPermissions $permissions,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly TranslatorInterface $translator,
    ) {
        parent::__construct($auth, $urlGenerator);
    }

    public static function getActionName(): string
    {
        return 'holiday_workday_shift';
    }

    public function onActions(PageActionsEvent $event): void
    {
        $shift = $event->getPayload()['shift'] ?? null;
        if (!$shift instanceof WorkdayShift || $shift->getId() === null) {
            return;
        }

        $token = $this->csrfTokenManager->getToken(WorkdayShiftController::CSRF_ACTION)->getValue();

        if ($shift->getStatus() === AbsenceStatus::REQUESTED && $this->permissions->canApprove($shift)) {
            // Reversible: kit.js runs it immediately (data-kpu-post), the result toast offers "Undo"
            $event->addAction('success', [
                'url' => '#',
                'title' => 'approve',
                'attr' => [
                    'data-kpu-post' => $this->path('holiday_workday_shift_bulk_approve'),
                    'data-kpu-token' => $token,
                    'data-kpu-ids' => (string) $shift->getId(),
                ],
            ]);
            $event->addAction('rejected', [
                'url' => '#',
                'title' => 'reject',
                'attr' => [
                    'data-kpu-post' => $this->path('holiday_workday_shift_bulk_reject'),
                    'data-kpu-token' => $token,
                    'data-kpu-ids' => (string) $shift->getId(),
                ],
            ]);
        }

        if ($this->permissions->canDelete($shift)) {
            $event->addAction('trash', [
                'url' => '#',
                'class' => 'text-red',
                'title' => 'delete',
                'attr' => [
                    'data-kpu-question' => $this->translator->trans('holiday.shift.delete_question'),
                    'data-kpu-post' => $this->path('holiday_workday_shift_delete', ['id' => $shift->getId()]),
                    'data-kpu-token' => $token,
                ],
            ]);
        }
    }
}
