<?php

namespace KimaiPlugin\HolidayBundle\Enum;

enum CalculationMode: string
{
    case COMPENSATE = 'compensate';
    case REDUCE = 'reduce';
}
