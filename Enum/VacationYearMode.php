<?php

namespace KimaiPlugin\HolidayBundle\Enum;

/** System setting: vacation year from the contract anniversary or the calendar year. */
enum VacationYearMode: string
{
    case EMPLOYMENT = 'employment';
    case CALENDAR = 'calendar';
}
