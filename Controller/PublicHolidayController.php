<?php

namespace KimaiPlugin\HolidayBundle\Controller;

use App\Controller\AbstractController;
use App\Utils\PageSetup;
use KimaiPlugin\HolidayBundle\Entity\PublicHoliday;
use KimaiPlugin\HolidayBundle\Entity\PublicHolidayGroup;
use KimaiPlugin\HolidayBundle\Form\PublicHolidayGroupType;
use KimaiPlugin\HolidayBundle\Form\PublicHolidayImportType;
use KimaiPlugin\HolidayBundle\Form\PublicHolidayType;
use KimaiPlugin\HolidayBundle\Repository\PublicHolidayGroupRepository;
use KimaiPlugin\HolidayBundle\Repository\PublicHolidayRepository;
use KimaiPlugin\HolidayBundle\Service\HolidayImporter;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route(path: '/holiday/public-holidays')]
#[IsGranted('edit_public_holidays')]
class PublicHolidayController extends AbstractController
{
    use HolidayUiTrait;

    public const CSRF_ID = 'holiday_public_holiday';

    public function __construct(
        private readonly PublicHolidayGroupRepository $groupRepository,
        private readonly PublicHolidayRepository $holidayRepository,
        private readonly HolidayImporter $importer,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route(path: '/{year}', name: 'holiday_public_holidays', defaults: ['year' => null], methods: ['GET'], requirements: ['year' => '\d{4}'])]
    public function index(Request $request, ?int $year = null): Response
    {
        $year ??= (int) date('Y');
        $groups = $this->groupRepository->findAllOrdered();
        $groupId = $request->query->getInt('group');
        $group = null;

        if ($groupId > 0) {
            $group = $this->groupRepository->find($groupId);
        }
        // Unknown or just deleted group: fall back to the first one
        if ($group === null && $groups !== []) {
            $group = $groups[0];
        }

        $holidays = $group !== null ? $this->holidayRepository->findByGroupAndYear($group, $year) : [];
        $groupParam = $group?->getId();
        $currentYear = (int) date('Y');

        $page = new PageSetup($this->translator->trans('holiday.page.public_holidays', ['%year%' => $year]));
        $page->setActionName('holiday_public_holidays');
        $page->setActionPayload(['group' => $group, 'year' => $year]);
        $page->setHelp($this->helpUrl('public-holidays'));

        return $this->render('@Holiday/public_holiday/index.html.twig', [
            'page_setup' => $page,
            'year' => $year,
            'groups' => $groups,
            'group' => $group,
            'holidays' => $holidays,
            'period' => [
                'prev' => $this->generateUrl('holiday_public_holidays', ['year' => $year - 1, 'group' => $groupParam]),
                'next' => $this->generateUrl('holiday_public_holidays', ['year' => $year + 1, 'group' => $groupParam]),
                'today' => $year === $currentYear ? null : $this->generateUrl('holiday_public_holidays', ['year' => $currentYear, 'group' => $groupParam]),
            ],
        ]);
    }

    #[Route(path: '/group/create', name: 'holiday_public_holiday_group_create', methods: ['GET', 'POST'])]
    public function createGroup(Request $request): Response
    {
        $group = new PublicHolidayGroup();
        $form = $this->createModalForm(PublicHolidayGroupType::class, $group, $this->generateUrl('holiday_public_holiday_group_create'));
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->groupRepository->save($group);

            return $this->formSuccess($request, 'holiday_public_holidays', ['group' => $group->getId()], false);
        }

        return $this->renderModalForm($form, 'holiday.public_holiday.create_group', $this->generateUrl('holiday_public_holidays'));
    }

    #[Route(path: '/group/{id}/holiday/create', name: 'holiday_public_holiday_create', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function createHoliday(Request $request, PublicHolidayGroup $group): Response
    {
        $year = $this->validYear($request);
        $holiday = new PublicHoliday();
        $holiday->setHolidayGroup($group);
        $holiday->setDate(new \DateTimeImmutable($year === (int) date('Y') ? 'today' : sprintf('%d-01-01', $year)));

        $form = $this->createModalForm(PublicHolidayType::class, $holiday, $this->generateUrl('holiday_public_holiday_create', ['id' => $group->getId(), 'year' => $year]));
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $holiday->setHolidayGroup($group);
            $this->holidayRepository->save($holiday);

            return $this->formSuccess($request, 'holiday_public_holidays', [
                'year' => (int) $holiday->getDate()?->format('Y'),
                'group' => $group->getId(),
            ], false);
        }

        return $this->renderModalForm($form, 'holiday.public_holiday.create', $this->generateUrl('holiday_public_holidays', ['year' => $year, 'group' => $group->getId()]), $group->getName());
    }

    #[Route(path: '/group/{id}/import', name: 'holiday_public_holiday_import', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function import(Request $request, PublicHolidayGroup $group): Response
    {
        $year = $this->validYear($request);
        $form = $this->createModalForm(PublicHolidayImportType::class, null, $this->generateUrl('holiday_public_holiday_import', ['id' => $group->getId(), 'year' => $year]), [
            'catalog_choices' => $this->importer->getCatalog()->getChoices(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $source = (string) $form->get('source')->getData();
            $customUrl = $form->get('customUrl')->getData();
            $importYear = (int) $form->get('year')->getData();

            try {
                $count = $this->importer->import(
                    $group,
                    $importYear,
                    $source !== 'custom' ? $source : null,
                    $source === 'custom' ? (string) $customUrl : null,
                );
                $this->addFlash('kpu_result', $this->translator->trans('holiday.import_success', ['%count%' => $count, '%year%' => $importYear]));

                return $this->formSuccess($request, 'holiday_public_holidays', ['year' => $importYear, 'group' => $group->getId()], false);
            } catch (\InvalidArgumentException|\RuntimeException $e) {
                $key = $this->errorKey($e) ?? 'holiday.error.ics_fetch_failed';
                $field = $key === 'holiday.error.ics_invalid_url' ? 'customUrl' : null;
                $error = new FormError($this->translator->trans($key, [], 'flashmessages'));
                $field !== null ? $form->get($field)->addError($error) : $form->addError($error);
            }
        }

        return $this->renderModalForm($form, 'holiday.public_holiday.import', $this->generateUrl('holiday_public_holidays', ['year' => $year, 'group' => $group->getId()]), $group->getName(), 'import');
    }

    #[Route(path: '/group/{id}/sync', name: 'holiday_public_holiday_group_sync', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function syncGroup(Request $request, PublicHolidayGroup $group): Response
    {
        $this->assertCsrf($request);
        $parameters = ['year' => $this->validYear($request), 'group' => $group->getId()];

        try {
            $count = $this->importer->sync($group);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            $message = $this->translator->trans($this->errorKey($e) ?? 'holiday.error.ics_fetch_failed', [], 'flashmessages');

            return $this->actionResult($request, $message, null, 'holiday_public_holidays', $parameters, 422);
        }

        return $this->actionResult($request, $this->translator->trans('holiday.sync_success', ['%count%' => $count]), null, 'holiday_public_holidays', $parameters);
    }

    #[Route(path: '/holiday/{id}/delete', name: 'holiday_public_holiday_delete', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function deleteHoliday(Request $request, PublicHoliday $holiday): Response
    {
        $parameters = ['year' => (int) $holiday->getDate()?->format('Y'), 'group' => $holiday->getHolidayGroup()?->getId()];

        if ($request->isMethod('POST')) {
            $this->assertCsrf($request);
            $this->holidayRepository->remove($holiday);
            $this->flashSuccess('action.delete.success');

            return $this->formSuccess($request, 'holiday_public_holidays', $parameters);
        }

        return $this->renderDelete(
            $this->generateUrl('holiday_public_holiday_delete', ['id' => $holiday->getId()]),
            $holiday,
            'holiday.public_holiday.delete_message',
            $this->generateUrl('holiday_public_holidays', $parameters)
        );
    }

    #[Route(path: '/group/{id}/delete', name: 'holiday_public_holiday_group_delete', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function deleteGroup(Request $request, PublicHolidayGroup $group): Response
    {
        if ($request->isMethod('POST')) {
            $this->assertCsrf($request);
            $this->groupRepository->remove($group);
            $this->flashSuccess('action.delete.success');

            return $this->formSuccess($request, 'holiday_public_holidays', [], false);
        }

        return $this->renderDelete(
            $this->generateUrl('holiday_public_holiday_group_delete', ['id' => $group->getId()]),
            $group,
            'holiday.public_holiday.delete_group_message',
            $this->generateUrl('holiday_public_holidays', ['group' => $group->getId()])
        );
    }

    private function createModalForm(string $type, mixed $data, string $action, array $options = []): FormInterface
    {
        return $this->createForm($type, $data, array_merge([
            'action' => $action,
            'method' => 'POST',
            'attr' => ['data-form-event' => AbsenceController::UPDATE_EVENT],
        ], $options));
    }

    private function renderModalForm(FormInterface $form, string $title, string $back, ?string $context = null, string $submit = 'action.save'): Response
    {
        $page = new PageSetup($title);
        $page->setHelp($this->helpUrl('public-holidays'));

        $title = $this->translator->trans($title);

        return $this->render('@Holiday/public_holiday/form.html.twig', [
            'page_setup' => $page,
            'form' => $form->createView(),
            'title' => $context !== null ? $title . ' · ' . $context : $title,
            'back' => $back,
            'submit' => $submit,
        ]);
    }

    private function renderDelete(string $action, PublicHoliday|PublicHolidayGroup $subject, string $message, string $back): Response
    {
        $form = $this->container->get('form.factory')->createNamed('', FormType::class, null, [
            'action' => $action,
            'method' => 'POST',
            'csrf_field_name' => '_token',
            'csrf_token_id' => self::CSRF_ID,
            'attr' => ['data-form-event' => AbsenceController::UPDATE_EVENT],
        ]);

        $page = new PageSetup('holiday.menu.public_holidays');
        $page->setHelp($this->helpUrl('public-holidays'));

        return $this->render('@Holiday/public_holiday/delete.html.twig', [
            'page_setup' => $page,
            'form' => $form->createView(),
            'subject' => $subject,
            'message' => $message,
            'back' => $back,
        ]);
    }

    private function validYear(Request $request): int
    {
        $year = $request->query->getInt('year');

        return $year >= 1000 && $year <= 9999 ? $year : (int) date('Y');
    }

    private function assertCsrf(Request $request): void
    {
        if (!$this->isCsrfTokenValid(self::CSRF_ID, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token');
        }
    }
}
