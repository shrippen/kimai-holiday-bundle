<?php

namespace KimaiPlugin\HolidayBundle\EventSubscriber\Actions;

use App\Event\PageActionsEvent;
use App\EventSubscriber\Actions\AbstractActionsSubscriber;
use KimaiPlugin\HolidayBundle\Controller\PublicHolidayController;
use KimaiPlugin\HolidayBundle\Entity\PublicHolidayGroup;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Page actions of the public holidays administration.
 */
final class PublicHolidayPageSubscriber extends AbstractActionsSubscriber
{
    public function __construct(
        AuthorizationCheckerInterface $auth,
        UrlGeneratorInterface $urlGenerator,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
        parent::__construct($auth, $urlGenerator);
    }

    public static function getActionName(): string
    {
        return 'holiday_public_holidays';
    }

    public function onActions(PageActionsEvent $event): void
    {
        if (!$this->isGranted('edit_public_holidays')) {
            return;
        }

        $payload = $event->getPayload();
        $group = $payload['group'] ?? null;
        $year = (int) ($payload['year'] ?? date('Y'));

        if ($group instanceof PublicHolidayGroup && $group->getId() !== null) {
            $event->addAction('create', [
                'url' => $this->path('holiday_public_holiday_create', ['id' => $group->getId(), 'year' => $year]),
                'class' => 'modal-ajax-form',
                'title' => 'holiday.public_holiday.create',
                'translation_domain' => 'messages',
            ]);
            $event->addAction('import', [
                'url' => $this->path('holiday_public_holiday_import', ['id' => $group->getId(), 'year' => $year]),
                'class' => 'modal-ajax-form',
                'title' => 'holiday.public_holiday.import',
                'translation_domain' => 'messages',
            ]);
            if ($group->getIcsUrl() !== null && $group->getIcsUrl() !== '') {
                $event->addAction('repeat', [
                    'url' => '#',
                    'title' => 'holiday.public_holiday.sync',
                    'translation_domain' => 'messages',
                    'attr' => [
                        'data-holiday-post' => $this->path('holiday_public_holiday_group_sync', ['id' => $group->getId(), 'year' => $year]),
                        'data-token' => $this->csrfTokenManager->getToken(PublicHolidayController::CSRF_ID)->getValue(),
                    ],
                ]);
            }
        }

        $event->addAction('widget_add', [
            'url' => $this->path('holiday_public_holiday_group_create'),
            'class' => 'modal-ajax-form',
            'title' => 'holiday.public_holiday.create_group',
            'translation_domain' => 'messages',
        ]);
    }
}
