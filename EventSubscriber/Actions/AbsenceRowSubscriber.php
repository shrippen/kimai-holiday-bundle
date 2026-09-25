<?php

namespace KimaiPlugin\HolidayBundle\EventSubscriber\Actions;

use App\Event\PageActionsEvent;
use App\EventSubscriber\Actions\AbstractActionsSubscriber;
use KimaiPlugin\HolidayBundle\Controller\AbsenceController;
use KimaiPlugin\HolidayBundle\Entity\Absence;
use KimaiPlugin\HolidayBundle\Enum\AbsenceStatus;
use KimaiPlugin\HolidayBundle\Service\AbsencePermissions;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Row menu ("…") of the absence list.
 */
final class AbsenceRowSubscriber extends AbstractActionsSubscriber
{
    public function __construct(
        AuthorizationCheckerInterface $auth,
        UrlGeneratorInterface $urlGenerator,
        private readonly AbsencePermissions $permissions,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
        parent::__construct($auth, $urlGenerator);
    }

    public static function getActionName(): string
    {
        return 'holiday_absence';
    }

    public function onActions(PageActionsEvent $event): void
    {
        $absence = $event->getPayload()['absence'] ?? null;
        if (!$absence instanceof Absence || $absence->getId() === null) {
            return;
        }

        if ($this->permissions->canEdit($absence)) {
            $event->addEdit($this->path('holiday_absence_edit', ['id' => $absence->getId()]));
        }

        if ($absence->getStatus() === AbsenceStatus::REQUESTED && $this->permissions->canApprove($absence)) {
            $token = $this->csrfTokenManager->getToken(AbsenceController::CSRF_ACTION)->getValue();
            // Reversible: runs immediately, the result toast offers "Undo" (see _scripts.html.twig)
            $event->addAction('success', [
                'url' => '#',
                'title' => 'approve',
                'attr' => [
                    'data-holiday-post' => $this->path('holiday_absence_bulk_approve'),
                    'data-token' => $token,
                    'data-ids' => (string) $absence->getId(),
                ],
            ]);
            $event->addAction('rejected', [
                'url' => '#',
                'title' => 'reject',
                'attr' => [
                    'data-holiday-post' => $this->path('holiday_absence_bulk_reject'),
                    'data-token' => $token,
                    'data-ids' => (string) $absence->getId(),
                ],
            ]);
        }

        if ($this->permissions->canDelete($absence)) {
            $event->addDelete($this->path('holiday_absence_delete', ['id' => $absence->getId()]));
        }
    }
}
