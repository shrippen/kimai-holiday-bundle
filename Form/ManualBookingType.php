<?php

namespace KimaiPlugin\HolidayBundle\Form;

use App\Form\Type\DatePickerType;
use KimaiPlugin\HolidayBundle\Enum\ManualBookingKind;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ManualBookingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('kind', EnumType::class, [
                'class' => ManualBookingKind::class,
                'label' => 'booking.kind',
                'mapped' => false,
                'choice_label' => fn (ManualBookingKind $kind) => $kind->label(),
                'choice_translation_domain' => 'messages',
            ])
            ->add('amount', NumberType::class, [
                'label' => 'booking.amount',
                'mapped' => false,
                'help' => 'booking.amount_help',
            ])
            ->add('bookingDate', DatePickerType::class, [
                'label' => 'booking.date',
                'input' => 'datetime_immutable',
                'mapped' => false,
            ])
            ->add('comment', TextareaType::class, [
                'label' => 'booking.comment',
                'mapped' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'translation_domain' => 'messages',
        ]);
    }
}
