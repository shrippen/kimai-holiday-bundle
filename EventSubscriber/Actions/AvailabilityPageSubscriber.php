<?php

namespace KimaiPlugin\HolidayBundle\EventSubscriber\Actions;

use App\Event\PageActionsEvent;
use App\EventSubscriber\Actions\AbstractActionsSubscriber;
use KimaiPlugin\HolidayBundle\Service\WorkdayShiftPermissions;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Page actions of the availability view: enter an extra shift, move a day.
 */
final class AvailabilityPageSubscriber extends AbstractActionsSubscriber
{
    public function __construct(
        AuthorizationCheckerInterface $auth,
        UrlGeneratorInterface $urlGenerator,
        private readonly WorkdayShiftPermissions $shiftPermissions,
    ) {
        parent::__construct($auth, $urlGenerator);
    }

    public static function getActionName(): string
    {
        return 'holiday_availability';
    }

    public function onActions(PageActionsEvent $event): void
    {
        $payload = $event->getPayload();
        $date = $payload['date'] ?? null;

        if ($payload['can_create'] ?? false) {
            $event->addAction('create', [
                'url' => $this->path('holiday_extra_shift_create', ['date' => $date]),
                'class' => 'modal-ajax-form',
                'title' => 'holiday.extra_shift.create',
                'translation_domain' => 'messages',
            ]);
        }

        // moving a day needs a location, which the availability page only has when it knows a first open day
        if ($date !== null && $this->shiftPermissions->canCreateFor($this->shiftPermissions->getCurrentUser())) {
            $event->addAction('shift', [
                'url' => $this->path('holiday_workday_shift_create', ['from' => $date]),
                'class' => 'modal-ajax-form',
                'title' => 'holiday.shift.create',
                'translation_domain' => 'messages',
            ]);
        }
    }
}
