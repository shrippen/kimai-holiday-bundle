<?php

namespace KimaiPlugin\HolidayBundle\Form;

use App\Form\Type\DatePickerType;
use App\Form\Type\UserType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotNull;

/**
 * Day shift. Data: ['user' => User, 'fromDate' => \DateTimeImmutable, 'toDate' => ?\DateTimeImmutable, 'comment' => ?string].
 * The user field only exists when the current user may create for others.
 */
class WorkdayShiftType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($options['choose_user']) {
            $builder->add('user', UserType::class, [
                'label' => 'holiday.shift.user',
                'constraints' => [new NotNull()],
            ]);
        }

        $builder
            ->add('fromDate', DatePickerType::class, [
                'label' => 'holiday.shift.from_date',
                'input' => 'datetime_immutable',
                'constraints' => [new NotNull()],
            ])
            ->add('toDate', DatePickerType::class, [
                'label' => 'holiday.shift.to_date',
                'input' => 'datetime_immutable',
                'constraints' => [new NotNull()],
            ])
            ->add('comment', TextareaType::class, [
                'label' => 'holiday.shift.comment',
                'required' => false,
                'attr' => ['rows' => 2],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'translation_domain' => 'messages',
            'choose_user' => false,
        ]);
        $resolver->setAllowedTypes('choose_user', 'bool');
    }
}
