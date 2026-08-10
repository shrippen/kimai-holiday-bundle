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
use Symfony\Component\Form\FormFactoryInterface;
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
    public function __construct(
        private readonly PublicHolidayGroupRepository $groupRepository,
        private readonly PublicHolidayRepository $holidayRepository,
        private readonly HolidayImporter $importer,
        private readonly FormFactoryInterface $formFactory,
        private readonly TranslatorInterface $translator,
    ) {
    }

    private function namedForm(string $name, string $type, mixed $data = null, array $options = []): FormInterface
    {
        return $this->formFactory->createNamed($name, $type, $data, $options);
    }

    #[Route(path: '/{year}', name: 'holiday_public_holidays', defaults: ['year' => null], methods: ['GET', 'POST'])]
    public function index(Request $request, ?int $year = null): Response
    {
        $year ??= (int) date('Y');
        $groups = $this->groupRepository->findAllOrdered();
        $groupId = $request->query->getInt('group');
        $group = null;

        if ($groupId > 0) {
            $group = $this->groupRepository->find($groupId);
        } elseif ($groups !== []) {
            $group = $groups[0];
        }

        $holidays = $group !== null ? $this->holidayRepository->findByGroupAndYear($group, $year) : [];

        $groupForm = $this->namedForm('group_form', PublicHolidayGroupType::class, new PublicHolidayGroup());
        $groupForm->handleRequest($request);
        if ($groupForm->isSubmitted() && $groupForm->isValid()) {
            /** @var PublicHolidayGroup $newGroup */
            $newGroup = $groupForm->getData();
            $this->groupRepository->save($newGroup);
            $this->flashSuccess('action.update.success');

            return $this->redirectToRoute('holiday_public_holidays', ['year' => $year, 'group' => $newGroup->getId()]);
        }

        $holiday = new PublicHoliday();
        if ($group !== null) {
            $holiday->setHolidayGroup($group);
        }
        $holidayForm = $this->namedForm('holiday_form', PublicHolidayType::class, $holiday);
        $holidayForm->handleRequest($request);
        if ($holidayForm->isSubmitted() && $holidayForm->isValid() && $group !== null) {
            $holiday->setHolidayGroup($group);
            $this->holidayRepository->save($holiday);
            $this->flashSuccess('action.update.success');

            return $this->redirectToRoute('holiday_public_holidays', ['year' => $year, 'group' => $group->getId()]);
        }

        $importForm = $this->namedForm('import_form', PublicHolidayImportType::class, null, [
            'catalog_choices' => $this->importer->getCatalog()->getChoices(),
        ]);
        $importForm->handleRequest($request);
        if ($importForm->isSubmitted() && $importForm->isValid() && $group !== null) {
            $source = (string) $importForm->get('source')->getData();
            $customUrl = $importForm->get('customUrl')->getData();
            $importYear = (int) $importForm->get('year')->getData();

            try {
                $count = $this->importer->import(
                    $group,
                    $importYear,
                    $source !== 'custom' ? $source : null,
                    $source === 'custom' ? (string) $customUrl : null,
                );
                $this->addFlash('success', $this->translator->trans('holiday.import_success', ['%count%' => $count]));
            } catch (\Throwable $e) {
                $this->flashError($e->getMessage());
            }

            return $this->redirectToRoute('holiday_public_holidays', [
                'year' => $importYear,
                'group' => $group->getId(),
            ]);
        }

        $page = new PageSetup('menu.public_holidays');

        return $this->render('@Holiday/public_holiday/index.html.twig', [
            'page_setup' => $page,
            'year' => $year,
            'groups' => $groups,
            'group' => $group,
            'holidays' => $holidays,
            'group_form' => $groupForm->createView(),
            'holiday_form' => $holidayForm->createView(),
            'import_form' => $importForm->createView(),
        ]);
    }

    #[Route(path: '/group/{id}/sync', name: 'holiday_public_holiday_group_sync', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function syncGroup(PublicHolidayGroup $group): Response
    {
        try {
            $count = $this->importer->sync($group);
            $this->addFlash('success', $this->translator->trans('holiday.sync_success', ['%count%' => $count]));
        } catch (\Throwable $e) {
            $this->flashError($e->getMessage());
        }

        return $this->redirectToRoute('holiday_public_holidays', [
            'year' => $group->getIcsFromYear() ?? (int) date('Y'),
            'group' => $group->getId(),
        ]);
    }

    #[Route(path: '/holiday/{id}/delete', name: 'holiday_public_holiday_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function deleteHoliday(PublicHoliday $holiday): Response
    {
        $groupId = $holiday->getHolidayGroup()?->getId();
        $year = (int) $holiday->getDate()?->format('Y');
        $this->holidayRepository->remove($holiday);
        $this->flashSuccess('action.delete.success');

        return $this->redirectToRoute('holiday_public_holidays', ['year' => $year, 'group' => $groupId]);
    }

    #[Route(path: '/group/{id}/delete', name: 'holiday_public_holiday_group_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function deleteGroup(PublicHolidayGroup $group): Response
    {
        $this->groupRepository->remove($group);
        $this->flashSuccess('action.delete.success');

        return $this->redirectToRoute('holiday_public_holidays');
    }
}
