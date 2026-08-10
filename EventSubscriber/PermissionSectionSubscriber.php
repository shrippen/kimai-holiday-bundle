<?php

namespace KimaiPlugin\HolidayBundle\EventSubscriber;

use App\Event\PermissionSectionsEvent;
use App\Model\PermissionSection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PermissionSectionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            PermissionSectionsEvent::class => ['onEvent', 100],
        ];
    }

    public function onEvent(PermissionSectionsEvent $event): void
    {
        $event->addSection(new PermissionSection('holiday.permission_section', [
            'hours_',
            'contract',
            'absence',
            'public_holiday',
            'workdays_override',
            'booking_contract',
            'times_contract',
        ]));
    }
}
