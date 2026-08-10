<?php

namespace KimaiPlugin\HolidayBundle\Ics;

use KimaiPlugin\HolidayBundle\Service\UserIcsCalendarBuilder;
use KimaiPlugin\HolidayBundle\Service\UserIcsTokenService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public ICS feed authenticated by per-user token (no Kimai login).
 */
class UserIcsController extends AbstractController
{
    public function __construct(
        private readonly UserIcsTokenService $tokenService,
        private readonly UserIcsCalendarBuilder $calendarBuilder,
    ) {
    }

    #[Route(path: '/holiday/ics/{token}.ics', name: 'holiday_user_ics', methods: ['GET'], requirements: ['token' => '[a-f0-9]{48}'])]
    #[Route(path: '/holiday/ics/{token}', name: 'holiday_user_ics_plain', methods: ['GET'], requirements: ['token' => '[a-f0-9]{48}'])]
    public function feed(string $token): Response
    {
        $user = $this->tokenService->findUserByToken($token);
        if ($user === null) {
            throw new NotFoundHttpException('Unknown calendar token');
        }

        $body = $this->calendarBuilder->build($user);

        return new Response($body, 200, [
            'Content-Type' => 'text/calendar; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="holiday-absences.ics"',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
