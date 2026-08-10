<?php

namespace KimaiPlugin\HolidayBundle\EventSubscriber\Actions;

use App\Event\PageActionsEvent;
use App\EventSubscriber\Actions\AbstractActionsSubscriber;

/**
 * Toolbar links on Kimai's built-in Arbeitszeiten (/contract) page.
 */
final class ContractLinksSubscriber extends AbstractActionsSubscriber
{
    public static function getActionName(): string
    {
        return 'contract_links';
    }

    public function onActions(PageActionsEvent $event): void
    {
        $payload = $event->getPayload();
        $user = $payload['user'] ?? null;
        $year = $payload['year'] ?? null;

        if ($user === null) {
            return;
        }

        $yearNumber = $year instanceof \DateTimeInterface
            ? (int) $year->format('Y')
            : (int) date('Y');

        if ($this->isGranted('absence')) {
            $event->addAction('holiday_absence', [
                'url' => $this->path('holiday_absence', ['year' => $yearNumber, 'user' => $user->getId()]),
                'title' => 'menu.absence',
                'translation_domain' => 'messages',
                'icon' => 'fas fa-umbrella-beach',
            ]);
        }

        if ($this->isGranted('create_booking_contract')) {
            $event->addAction('holiday_booking', [
                'url' => $this->path('holiday_booking_create', ['user' => $user->getId()]),
                'title' => 'booking.create',
                'translation_domain' => 'messages',
                'icon' => 'fas fa-plus',
            ]);
        }
    }
}
