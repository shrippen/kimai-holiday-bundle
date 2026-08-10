<?php

namespace KimaiPlugin\HolidayBundle\EventSubscriber;

use App\Event\TimesheetCreatePreEvent;
use App\Event\TimesheetUpdatePreEvent;
use KimaiPlugin\HolidayBundle\Service\HolidayConfiguration;
use KimaiPlugin\HolidayBundle\Service\UserWorkContract;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class TimesheetValidationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly HolidayConfiguration $configuration,
        private readonly UserWorkContract $userWorkContract,
        private readonly AuthorizationCheckerInterface $security,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TimesheetCreatePreEvent::class => ['onCreate', 100],
            TimesheetUpdatePreEvent::class => ['onUpdate', 100],
        ];
    }

    public function onCreate(TimesheetCreatePreEvent $event): void
    {
        $this->validate($event->getTimesheet());
    }

    public function onUpdate(TimesheetUpdatePreEvent $event): void
    {
        $this->validate($event->getTimesheet());
    }

    private function validate(object $timesheet): void
    {
        if (!$this->configuration->restrictTimesheetsToWorkdays()) {
            return;
        }

        if ($this->security->isGranted('workdays_override_timesheet')) {
            return;
        }

        if (!method_exists($timesheet, 'getUser') || !method_exists($timesheet, 'getBegin')) {
            return;
        }

        $user = $timesheet->getUser();
        $begin = $timesheet->getBegin();
        if ($user === null || $begin === null) {
            return;
        }

        if (!$this->userWorkContract->hasWorkingTimeConfigured($user)) {
            return;
        }

        if ($this->userWorkContract->getExpectedSecondsForDate($user, $begin) <= 0) {
            throw new \InvalidArgumentException('holiday.error.timesheet_non_workday');
        }
    }
}
