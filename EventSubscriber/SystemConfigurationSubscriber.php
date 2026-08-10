<?php

namespace KimaiPlugin\HolidayBundle\EventSubscriber;

use App\Event\SystemConfigurationEvent;
use App\Form\Model\Configuration;
use App\Form\Model\SystemConfiguration as SystemConfigurationModel;
use KimaiPlugin\HolidayBundle\Enum\CalculationMode;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;

class SystemConfigurationSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            SystemConfigurationEvent::class => ['onSystemConfiguration', 100],
        ];
    }

    public function onSystemConfiguration(SystemConfigurationEvent $event): void
    {
        $modes = [
            'holiday.calc_mode.compensate' => CalculationMode::COMPENSATE->value,
            'holiday.calc_mode.reduce' => CalculationMode::REDUCE->value,
        ];

        $modeOptions = [
            'choices' => $modes,
            'choice_translation_domain' => 'messages',
        ];

        $event->addConfiguration(
            (new SystemConfigurationModel('holiday'))
                ->setTranslation('holiday.settings_section')
                ->setTranslationDomain('messages')
                ->setConfiguration([
                    (new Configuration('holiday.absence_comment_required'))
                        ->setLabel('holiday.absence_comment_required')
                        ->setTranslationDomain('messages')
                        ->setRequired(false)
                        ->setType(CheckboxType::class),
                    (new Configuration('holiday.allow_half_day_vacation'))
                        ->setLabel('holiday.allow_half_day_vacation')
                        ->setTranslationDomain('messages')
                        ->setRequired(false)
                        ->setType(CheckboxType::class)
                        ->setValue(true),
                    (new Configuration('holiday.restrict_timesheets_to_workdays'))
                        ->setLabel('holiday.restrict_timesheets_to_workdays')
                        ->setTranslationDomain('messages')
                        ->setRequired(false)
                        ->setType(CheckboxType::class),
                    (new Configuration('holiday.absence_project_id'))
                        ->setLabel('holiday.absence_project_id')
                        ->setTranslationDomain('messages')
                        ->setRequired(false)
                        ->setType(IntegerType::class),
                    (new Configuration('holiday.absence_activity_id'))
                        ->setLabel('holiday.absence_activity_id')
                        ->setTranslationDomain('messages')
                        ->setRequired(false)
                        ->setType(IntegerType::class),
                ])
        );

        $event->addConfiguration(
            (new SystemConfigurationModel('holiday_calculation'))
                ->setTranslation('holiday.calculation_modes_section')
                ->setTranslationDomain('messages')
                ->setConfiguration([
                    (new Configuration('holiday.calculation_mode_vacation'))
                        ->setLabel('holiday.calculation_mode_vacation')
                        ->setTranslationDomain('messages')
                        ->setRequired(false)
                        ->setType(ChoiceType::class)
                        ->setOptions($modeOptions + ['help' => 'holiday.calculation_modes_help']),
                    (new Configuration('holiday.calculation_mode_sickness'))
                        ->setLabel('holiday.calculation_mode_sickness')
                        ->setTranslationDomain('messages')
                        ->setRequired(false)
                        ->setType(ChoiceType::class)
                        ->setOptions($modeOptions),
                    (new Configuration('holiday.calculation_mode_sickness_relative'))
                        ->setLabel('holiday.calculation_mode_sickness_relative')
                        ->setTranslationDomain('messages')
                        ->setRequired(false)
                        ->setType(ChoiceType::class)
                        ->setOptions($modeOptions),
                    (new Configuration('holiday.calculation_mode_other'))
                        ->setLabel('holiday.calculation_mode_other')
                        ->setTranslationDomain('messages')
                        ->setRequired(false)
                        ->setType(ChoiceType::class)
                        ->setOptions($modeOptions),
                    (new Configuration('holiday.calculation_mode_public_holiday'))
                        ->setLabel('holiday.calculation_mode_public_holiday')
                        ->setTranslationDomain('messages')
                        ->setRequired(false)
                        ->setType(ChoiceType::class)
                        ->setOptions($modeOptions),
                ])
        );
    }
}
