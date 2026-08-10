<?php

namespace KimaiPlugin\HolidayBundle\Form;

use KimaiPlugin\HolidayBundle\Entity\PublicHolidayGroup;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PublicHolidayGroupType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'public_holiday.group_name'])
            ->add('country', TextType::class, [
                'label' => 'public_holiday.country',
                'required' => false,
                'help' => 'public_holiday.country_help',
            ])
            ->add('region', TextType::class, [
                'label' => 'public_holiday.region',
                'required' => false,
                'help' => 'public_holiday.region_help',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PublicHolidayGroup::class,
            'translation_domain' => 'messages',
        ]);
    }
}
