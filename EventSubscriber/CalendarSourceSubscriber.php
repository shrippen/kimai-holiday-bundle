<?php

namespace KimaiPlugin\HolidayBundle\EventSubscriber;

use App\Calendar\CalendarSource;
use App\Calendar\CalendarSourceType;
use App\Event\CalendarSourceEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class CalendarSourceSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly AuthorizationCheckerInterface $security,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CalendarSourceEvent::class => ['onCalendarSource', 100],
        ];
    }

    public function onCalendarSource(CalendarSourceEvent $event): void
    {
        if (!$this->security->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            return;
        }

        if ($this->security->isGranted('absence')) {
            $uri = $this->urlGenerator->generate('holiday_calendar_absences', [], UrlGeneratorInterface::ABSOLUTE_URL);
            $event->addSource(new CalendarSource(
                CalendarSourceType::JSON,
                'holiday_absences',
                $uri,
                '#4e73df'
            ));
        }

        if ($this->security->isGranted('hours_own_profile') || $this->security->isGranted('hours') || $this->security->isGranted('absence')) {
            $uri = $this->urlGenerator->generate('holiday_calendar_public_holidays', [], UrlGeneratorInterface::ABSOLUTE_URL);
            $event->addSource(new CalendarSource(
                CalendarSourceType::JSON,
                'holiday_public_holidays',
                $uri,
                '#e74a3b'
            ));
        }
    }
}
