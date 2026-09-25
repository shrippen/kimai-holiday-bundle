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
     * After a successful modal form: answer without a redirect, so the flash messages survive until the page
     * reloads (a followed redirect would render and consume them inside the modal request). Kimai's modal
     * plugin treats a response without form as success, fires the form's data-form-event and closes the modal.
     */
    private function formSuccess(Request $request, string $route, array $parameters = []): Response
    {
        if ($this->isModalRequest($request)) {
            return new Response('');
        }

        return $this->redirectToRoute($route, $parameters);
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
