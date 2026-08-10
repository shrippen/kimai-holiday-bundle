<?php

namespace KimaiPlugin\HolidayBundle\Form;

use App\Form\Type\DatePickerType;
use KimaiPlugin\HolidayBundle\Entity\PublicHoliday;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PublicHolidayType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('date', DatePickerType::class, [
                'label' => 'public_holiday.date',
                'input' => 'datetime_immutable',
            ])
            ->add('name', TextType::class, ['label' => 'public_holiday.name'])
            ->add('halfDay', CheckboxType::class, [
                'label' => 'public_holiday.half_day',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PublicHoliday::class,
            'translation_domain' => 'messages',
        ]);
    }
}
