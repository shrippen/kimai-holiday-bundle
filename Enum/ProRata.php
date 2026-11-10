<?php

namespace KimaiPlugin\HolidayBundle\Enum;

/** How a vacation year is shortened when the contract starts or ends within it. */
enum ProRata
{
    case MONTHS;
    case DAYS;
}
