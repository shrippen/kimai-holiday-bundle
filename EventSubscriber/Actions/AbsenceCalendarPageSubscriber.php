<?php

namespace KimaiPlugin\HolidayBundle\EventSubscriber\Actions;

use App\Event\PageActionsEvent;
use App\EventSubscriber\Actions\AbstractActionsSubscriber;

/**
 * Page actions of the absence calendar report.
 */
final class AbsenceCalendarPageSubscriber extends AbstractActionsSubscriber
{
    public static function getActionName(): string
    {
        return 'holiday_absence_calendar';
    }

    public function onActions(PageActionsEvent $event): void
    {
        if (!$this->isGranted('absence')) {
            return;
        }

        $year = (int) ($event->getPayload()['year'] ?? date('Y'));
        $event->addAction('holiday', [
            'url' => $this->path('holiday_absence', ['year' => $year]),
            'title' => 'holiday.menu.absence',
            'translation_domain' => 'messages',
        ]);
    }
}
