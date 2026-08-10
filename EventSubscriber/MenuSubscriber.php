<?php

namespace KimaiPlugin\HolidayBundle\EventSubscriber;

use App\Event\ConfigureMainMenuEvent;
use App\Utils\MenuItemModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Extends Kimai's built-in Arbeitsvertrag / Arbeitszeiten menu — does not create a parallel section.
 */
class MenuSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly AuthorizationCheckerInterface $security)
    {
    }

    public static function getSubscribedEvents(): array
    {
        // After Kimai core (priority 100) so the `contract` menu already exists.
        return [
            ConfigureMainMenuEvent::class => ['onMainMenu', -10],
        ];
    }

    public function onMainMenu(ConfigureMainMenuEvent $event): void
    {
        if (!$this->security->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            return;
        }

        if ($this->security->isGranted('absence')) {
            $contract = $event->findById('contract');
            if ($contract === null) {
                $contract = new MenuItemModel('contract', 'work_contract', null, [], 'contract');
                $event->getMenu()->addChild($contract);
            }

            if ($contract->getChild('holiday_absence') === null) {
                $absence = new MenuItemModel('holiday_absence', 'menu.absence', 'holiday_absence', [], 'fas fa-umbrella-beach');
                $absence->setTranslationDomain('messages');
                $contract->addChild($absence);
            }
        }

        if ($this->security->isGranted('absence')) {
            $reporting = $event->getReportingMenu();
            if ($reporting !== null && $reporting->getChild('absence_calendar_report') === null) {
                $item = new MenuItemModel('absence_calendar_report', 'menu.absence_calendar', 'holiday_absence_calendar', [], 'fas fa-calendar-week');
                $item->setTranslationDomain('messages');
                $reporting->addChild($item);
            }
        }

        if ($this->security->isGranted('edit_public_holidays')) {
            $admin = $event->getAdminMenu();
            if ($admin->getChild('public_holidays') === null) {
                $item = new MenuItemModel('public_holidays', 'menu.public_holidays', 'holiday_public_holidays', [], 'fas fa-calendar-day');
                $item->setTranslationDomain('messages');
                $admin->addChild($item);
            }
        }
    }
}
