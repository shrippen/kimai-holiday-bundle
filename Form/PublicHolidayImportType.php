<?php

namespace KimaiPlugin\HolidayBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class PublicHolidayImportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $catalogChoices = $options['catalog_choices'];
        $choices = array_merge(
            ['public_holiday.ics.custom' => 'custom'],
            $catalogChoices
        );

        $builder
            ->add('source', ChoiceType::class, [
                'label' => 'public_holiday.ics_source',
                'choices' => $choices,
                'choice_translation_domain' => 'messages',
                'help' => 'public_holiday.ics_source_help',
            ])
            ->add('customUrl', TextType::class, [
                'label' => 'public_holiday.ics_url',
                'required' => false,
                'help' => 'public_holiday.ics_url_help',
                'attr' => [
                    'placeholder' => 'https://example.com/holidays.ics',
                ],
            ])
            ->add('year', IntegerType::class, [
                'label' => 'public_holiday.year',
                'help' => 'public_holiday.year_help',
                'data' => (int) date('Y'),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'catalog_choices' => [],
            'translation_domain' => 'messages',
            'constraints' => [
                new Callback(static function (array $data, ExecutionContextInterface $context): void {
                    $source = $data['source'] ?? null;
                    $url = trim((string) ($data['customUrl'] ?? ''));
                    if ($source === 'custom' && $url === '') {
                        $context->buildViolation('holiday.error.ics_url_required')
                            ->atPath('[customUrl]')
                            ->addViolation();
                    }
                }),
            ],
        ]);
        $resolver->setAllowedTypes('catalog_choices', 'array');
    }
}
