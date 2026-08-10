<?php

namespace KimaiPlugin\HolidayBundle\Form\Extension;

use App\Form\Type\DatePickerType;
use App\Form\UserContractType;
use KimaiPlugin\HolidayBundle\Repository\PublicHolidayGroupRepository;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Adds vacation entitlement and public-holiday group to Kimai's built-in
 * user profile → Arbeitsvertrag form (does not duplicate weekday hours).
 */
class UserContractTypeExtension extends AbstractTypeExtension
{
    public function __construct(
        private readonly PublicHolidayGroupRepository $groupRepository,
    ) {
    }

    public static function getExtendedTypes(): iterable
    {
        return [UserContractType::class];
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $groupChoices = [];
        foreach ($this->groupRepository->findAllOrdered() as $group) {
            if ($group->getId() !== null) {
                $groupChoices[$group->getName()] = (string) $group->getId();
            }
        }

        $builder
            ->add('holidaysPerYear', NumberType::class, [
                'label' => 'contract.vacation_days',
                'help' => 'contract.vacation_days_help',
                'required' => false,
                'scale' => 1,
                'html5' => true,
                'translation_domain' => 'messages',
                'attr' => [
                    'min' => 0,
                    'step' => 0.5,
                ],
            ])
            ->add('publicHolidayGroup', ChoiceType::class, [
                'label' => 'contract.public_holiday_group',
                'help' => 'contract.public_holiday_group_help',
                'required' => false,
                'placeholder' => '',
                'choices' => $groupChoices,
                'translation_domain' => 'messages',
            ])
            ->add('workStartingDay', DatePickerType::class, [
                'label' => 'contract.start_date',
                'required' => false,
                'translation_domain' => 'messages',
            ])
            ->add('lastWorkingDay', DatePickerType::class, [
                'label' => 'contract.end_date',
                'required' => false,
                'translation_domain' => 'messages',
            ]);
    }
}
