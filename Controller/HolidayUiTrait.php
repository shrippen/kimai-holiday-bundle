<?php

namespace KimaiPlugin\HolidayBundle\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Response helpers for Kimai modals, kit result callouts and kit bulk actions (see kimai-plugin-ui GUIDELINES.md).
 */
trait HolidayUiTrait
{
    /** Plugin documentation, shown by Kimai's floating "?" button (PageSetup::setHelp). */
    private const HELP_URL = 'https://github.com/shrippen/kimai-holiday-bundle/blob/main/README.md';

    private function helpUrl(string $anchor): string
    {
        return self::HELP_URL . '#' . $anchor;
    }

    /** Request sent by Kimai's modal form plugin (modal-ajax-form). */
    private function isModalRequest(Request $request): bool
    {
        return str_contains(strtolower((string) $request->headers->get('X-Requested-With')), 'kimai-modal');
    }

    /** Request sent by the kit (KimaiPluginUi.post) or other fetch() calls that expect JSON. */
    private function wantsJson(Request $request): bool
    {
        return $request->isXmlHttpRequest()
            || str_contains((string) $request->headers->get('Accept'), 'application/json');
    }

    /**
     * Answer after a successful form whose result may set a kpu_result callout (kimai-plugin-ui GUIDELINES 3.6,
     * README "kpuFormSuccess"). A normal 302 would be followed inside Kimai's modal fetch and consume the flash.
     *
     * keepUrl = true : empty 200 in the modal; Kimai closes it and fires the form's data-form-event "kpu.reload"
     *                  (see modalFormOptions()), kit.js reloads the current page (filters and period stay).
     * keepUrl = false: 201 + x-modal-redirect (redirectToRouteAfterCreate), Kimai loads $route (e.g. the year or
     *                  group of the created entry).
     * Without modal (plain page) always a redirect to $route.
     */
    private function formSuccess(Request $request, string $route, array $parameters = [], bool $keepUrl = true): Response
    {
        if (!$this->isModalRequest($request)) {
            return $this->redirectToRoute($route, $parameters);
        }

        return $keepUrl ? new Response('') : $this->redirectToRouteAfterCreate($route, $parameters);
    }

    /**
     * Result of an immediate action: JSON for the kit ({message, undo}), otherwise a kpu_result callout and redirect.
     *
     * @param array{url: string, token: string, ids: list<int>}|null $undo
     */
    private function actionResult(Request $request, string $message, ?array $undo, string $route, array $parameters, int $status = 200): Response
    {
        if ($this->wantsJson($request)) {
            $data = ['message' => $message];
            if ($undo !== null) {
                $data['undo'] = $undo;
            }

            return new JsonResponse($data, $status);
        }

        $this->addFlash($status >= 400 ? 'error' : 'kpu_result', $message);

        return $this->redirectToRoute($route, $parameters);
    }

    /**
     * Services throw exceptions whose message is a translation key (holiday.error.*, domain flashmessages).
     * Anything else is not a key and must not be used as one.
     */
    private function errorKey(\Throwable $e): ?string
    {
        $message = $e->getMessage();

        return str_starts_with($message, 'holiday.error.') ? $message : null;
    }

    private function flashThrowable(\Throwable $e): void
    {
        $key = $this->errorKey($e);
        if ($key !== null) {
            $this->flashError($key);

            return;
        }

        if ($e instanceof \Exception) {
            $this->flashUpdateException($e);

            return;
        }

        throw $e;
    }
}
