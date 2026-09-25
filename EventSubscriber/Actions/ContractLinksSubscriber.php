<?php

namespace KimaiPlugin\HolidayBundle\EventSubscriber\Actions;

use App\Entity\User;
use App\Event\PageActionsEvent;
use App\EventSubscriber\Actions\AbstractActionsSubscriber;
use App\Model\Year;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Links next to the user/year picker on Kimai's built-in working times (/contract) page
 * (core template contract/status.html.twig, action "contract_links", payload {year: Year, user: User}).
 */
final class ContractLinksSubscriber extends AbstractActionsSubscriber
{
    public function __construct(
        AuthorizationCheckerInterface $auth,
        UrlGeneratorInterface $urlGenerator,
        private readonly TranslatorInterface $translator,
    ) {
        parent::__construct($auth, $urlGenerator);
    }

    public static function getActionName(): string
    {
        return 'contract_links';
    }

    public function onActions(PageActionsEvent $event): void
    {
        $payload = $event->getPayload();
        $user = $payload['user'] ?? null;
        $year = $payload['year'] ?? null;

        if (!$user instanceof User) {
            return;
        }

        if ($year instanceof Year) {
            $year = $year->getYear();
        }
        $yearNumber = $year instanceof \DateTimeInterface ? (int) $year->format('Y') : (int) date('Y');
        $userParam = $user->getId() === $event->getUser()->getId() ? null : $user->getId();

        // One dropdown only: core renders these links in a non-wrapping row next to the user/year picker (390 px).
        $locale = $event->getLocale() ?? $event->getUser()->getLocale();

        if ($this->isGranted('absence')) {
            $event->addActionToSubmenu('holiday', 'holiday_absence', [
                'url' => $this->path('holiday_absence', ['year' => $yearNumber, 'user' => $userParam]),
                'title' => 'holiday.menu.absence',
            ]);
        }

        if ($this->isGranted('create_booking_contract')) {
            $event->addActionToSubmenu('holiday', 'holiday_booking', [
                'url' => $this->path('holiday_booking_create', ['user' => $userParam]),
                'title' => 'holiday.booking.create',
            ]);
        }

        if ($this->isGranted('view_booking_contract')) {
            $formatter = new \IntlDateFormatter($locale, \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, 'UTC', null, 'LLLL yyyy');
            for ($month = 1; $month <= 12; ++$month) {
                $label = (string) $formatter->format(new \DateTimeImmutable(sprintf('%d-%02d-01', $yearNumber, $month), new \DateTimeZone('UTC')));
                $event->addActionToSubmenu('holiday', 'holiday_pdf_' . $month, [
                    'url' => $this->path('holiday_month_pdf', ['year' => $yearNumber, 'month' => $month, 'user' => $userParam]),
                    'title' => $this->translator->trans('holiday.pdf_month', ['%month%' => $label], 'messages', $locale),
                    'target' => '_blank',
                ]);
            }
        }

        if ($event->hasSubmenu('holiday')) {
            $event->replaceAction('holiday', array_merge($event->getActions()['holiday'], ['title' => 'holiday.menu.absence']));
        }
    }
}
