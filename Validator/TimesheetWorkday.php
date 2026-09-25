<?php

namespace KimaiPlugin\HolidayBundle\Validator;

use App\Validator\Constraints\TimesheetConstraint;

/**
 * Timesheets only on contractual workdays (system setting "restrict timesheets to workdays").
 * Registered via Kimai's TimesheetConstraint tag, so it shows up as a regular form/API validation error.
 */
final class TimesheetWorkday extends TimesheetConstraint
{
    public const NON_WORKDAY_ERROR = 'holiday-timesheet-non-workday-01';

    protected const ERROR_NAMES = [
        self::NON_WORKDAY_ERROR => 'NON_WORKDAY_ERROR',
    ];

    public string $message = 'holiday.error.timesheet_non_workday';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
