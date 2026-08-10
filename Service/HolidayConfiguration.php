<?php

namespace KimaiPlugin\HolidayBundle\Service;

use App\Configuration\SystemConfiguration;
use KimaiPlugin\HolidayBundle\Enum\AbsenceType;
use KimaiPlugin\HolidayBundle\Enum\CalculationMode;

class HolidayConfiguration
{
    public function __construct(private readonly SystemConfiguration $configuration)
    {
    }

    public function isCommentRequired(): bool
    {
        return (bool) ($this->configuration->find('holiday.absence_comment_required') ?? false);
    }

    public function allowHalfDayVacation(): bool
    {
        $value = $this->configuration->find('holiday.allow_half_day_vacation');

        return $value === null ? true : (bool) $value;
    }

    public function restrictTimesheetsToWorkdays(): bool
    {
        return (bool) ($this->configuration->find('holiday.restrict_timesheets_to_workdays') ?? false);
    }

    public function getAbsenceProjectId(): ?int
    {
        $id = $this->configuration->find('holiday.absence_project_id');

        return $id !== null && $id !== '' ? (int) $id : null;
    }

    public function getAbsenceActivityId(): ?int
    {
        $id = $this->configuration->find('holiday.absence_activity_id');

        return $id !== null && $id !== '' ? (int) $id : null;
    }

    public function getCalculationMode(AbsenceType|string $type): CalculationMode
    {
        $key = $type instanceof AbsenceType ? $type->value : $type;
        $value = $this->configuration->find('holiday.calculation_mode_' . $key);

        if ($value === CalculationMode::REDUCE->value) {
            return CalculationMode::REDUCE;
        }

        return CalculationMode::COMPENSATE;
    }

    public function getPublicHolidayCalculationMode(): CalculationMode
    {
        $value = $this->configuration->find('holiday.calculation_mode_public_holiday');

        if ($value === CalculationMode::REDUCE->value) {
            return CalculationMode::REDUCE;
        }

        return CalculationMode::COMPENSATE;
    }
}
