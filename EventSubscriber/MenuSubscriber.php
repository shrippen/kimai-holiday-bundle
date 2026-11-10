<?php

namespace KimaiPlugin\HolidayBundle\EventSubscriber;

use App\Event\ConfigureMainMenuEvent;
use App\Entity\User;
use App\Utils\MenuItemModel;
use KimaiPlugin\HolidayBundle\Service\OpenRequestCounter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Extends Kimai's built-in Arbeitsvertrag / Arbeitszeiten menu — does not create a parallel section.
 */
class MenuSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly AuthorizationCheckerInterface $security,
        private readonly Security $currentUser,
        private readonly OpenRequestCounter $openRequests,
    ) {
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
                $absence = new MenuItemModel('holiday_absence', 'holiday.menu.absence', 'holiday_absence', [], 'holiday');
                $absence->setTranslationDomain('messages');
                $contract->addChild($absence);
            }

            if ($this->security->isGranted('approve_other_absence') || $this->security->isGranted('approve_shift')) {
                $this->addRequestsItem($contract, $contract->getChild('holiday_absence'));
            }
        }

        if ($this->security->isGranted('absence')) {
            $reporting = $event->getReportingMenu();
            if ($reporting !== null && $reporting->getChild('absence_calendar_report') === null) {
                $item = new MenuItemModel('absence_calendar_report', 'holiday.menu.absence_calendar', 'holiday_absence_calendar', [], 'calendar');
                $item->setTranslationDomain('messages');
                $reporting->addChild($item);
            }
        }

        if ($this->security->isGranted('view_availability')) {
            $reporting = $event->getReportingMenu();
            if ($reporting !== null && $reporting->getChild('holiday_availability') === null) {
                $item = new MenuItemModel('holiday_availability', 'holiday.menu.availability', 'holiday_availability_current', [], 'user');
                $item->setTranslationDomain('messages');
                $reporting->addChild($item);
            }
        }

        // Kimai's "Reporting" entry only holds the plugin's pages now, so it is named after them
        $reporting = $event->getReportingMenu();
        if ($reporting !== null && $reporting->hasChildren()) {
            $reporting->setLabel('holiday.menu.planning');
        }

        if ($this->security->isGranted('edit_public_holidays')) {
            $admin = $event->getAdminMenu();
            if ($admin->getChild('public_holidays') === null) {
                $item = new MenuItemModel('public_holidays', 'holiday.menu.public_holidays', 'holiday_public_holidays', [], 'public-holiday');
                $item->setTranslationDomain('messages');
                $admin->addChild($item);
            }
        }

        if ($this->security->isGranted('view_location')) {
            $admin = $event->getAdminMenu();
            if ($admin->getChild('holiday_locations') === null) {
                $item = new MenuItemModel('holiday_locations', 'holiday.menu.locations', 'holiday_locations', [], 'pin');
                $item->setTranslationDomain('messages');
                $admin->addChild($item);
            }
        }
    }

    /**
     * "Open requests" entry plus a badge on the absence entry, so approvers notice requests without a mailer.
     */
    private function addRequestsItem(MenuItemModel $contract, ?MenuItemModel $absence): void
    {
        $user = $this->currentUser->getUser();
        $count = $user instanceof User ? $this->openRequests->countFor($user) : 0;

        if ($contract->getChild('holiday_absence_requests') === null) {
            $item = new MenuItemModel('holiday_absence_requests', 'holiday.menu.requests', 'holiday_absence_requests', [], 'holiday');
            $item->setTranslationDomain('messages');
            $contract->addChild($item);
            if ($count > 0) {
                $item->setBadge((string) $count);
                $item->setBadgeColor('yellow');
            }
        }

        if ($count > 0 && $absence !== null) {
            $absence->setBadge((string) $count);
            $absence->setBadgeColor('yellow');
        }
    }
}
