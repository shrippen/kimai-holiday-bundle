<?php

namespace KimaiPlugin\HolidayBundle\Form;

use KimaiPlugin\HolidayBundle\Entity\Absence;
use KimaiPlugin\HolidayBundle\Enum\AbsenceType;
use App\Form\Type\DatePickerType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AbsenceTypeForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type', EnumType::class, [
                'class' => AbsenceType::class,
                'label' => 'absence.type',
                'choice_label' => fn (AbsenceType $type) => $type->label(),
                'choice_attr' => fn (AbsenceType $type) => ['data-icon' => $type->icon()],
                'choice_translation_domain' => 'messages',
                'expanded' => true,
                'multiple' => false,
            ])
            ->add('startDate', DatePickerType::class, [
                'label' => 'absence.start',
                'input' => 'datetime_immutable',
            ])
            ->add('endDate', DatePickerType::class, [
                'label' => 'absence.end',
                'input' => 'datetime_immutable',
            ])
            ->add('halfDay', CheckboxType::class, [
                'label' => 'absence.half_day',
                'required' => false,
            ])
            ->add('duration', NumberType::class, [
                'label' => 'absence.duration',
                'required' => false,
                'help' => 'absence.duration_help',
                'scale' => 2,
                'html5' => true,
                'attr' => [
                    'min' => 0,
                    'step' => 0.25,
                ],
            ])
            ->add('comment', TextareaType::class, [
                'label' => 'absence.comment',
                'required' => false,
            ]);

        $builder->get('duration')->addModelTransformer(new CallbackTransformer(
            static function (?int $seconds): ?float {
                return $seconds === null ? null : round($seconds / 3600, 2);
            },
            static function (mixed $hours): ?int {
                if ($hours === null || $hours === '') {
                    return null;
                }

                return (int) round(((float) $hours) * 3600);
            }
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Absence::class,
            'translation_domain' => 'messages',
        ]);
    }
}
