<?php

namespace KimaiPlugin\HolidayBundle\EventSubscriber\Actions;

use App\Entity\User;
use App\Event\PageActionsEvent;
use App\EventSubscriber\Actions\AbstractActionsSubscriber;

/**
 * Page actions of the absence list (Kimai page header: icon buttons, "…" on mobile).
 */
final class AbsencePageSubscriber extends AbstractActionsSubscriber
{
    public static function getActionName(): string
    {
        return 'holiday_absences';
    }

    public function onActions(PageActionsEvent $event): void
    {
        $payload = $event->getPayload();
        $user = $payload['user'] ?? null;
        if (!$user instanceof User) {
            return;
        }

        $year = (int) ($payload['year'] ?? date('Y'));
        $userParam = $user->getId() === $event->getUser()->getId() ? null : $user->getId();

        if ($payload['can_create'] ?? false) {
            $event->addCreate($this->path('holiday_absence_create', ['user' => $userParam]));
        }

        if ($payload['can_ics'] ?? false) {
            $event->addAction('link', [
                'url' => $this->path('holiday_absence_ics', ['user' => $userParam, 'year' => $year]),
                'class' => 'modal-ajax-form',
                'title' => 'holiday.absence.ics.title',
                'translation_domain' => 'messages',
            ]);
        }

        $event->addQuickExport($this->path('holiday_absence_export', ['year' => $year, 'user' => $userParam]));

        if ($this->isGranted('absence')) {
            $event->addAction('calendar', [
                'url' => $this->path('holiday_absence_calendar', ['year' => $year]),
                'title' => 'holiday.menu.absence_calendar',
                'translation_domain' => 'messages',
            ]);
        }
    }
}
