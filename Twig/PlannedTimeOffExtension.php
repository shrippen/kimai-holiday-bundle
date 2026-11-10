<?php

namespace KimaiPlugin\HolidayBundle\Twig;

use App\WorkingTime\Model\Year;
use KimaiPlugin\HolidayBundle\Service\PlannedTimeOff;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/** Planned time off for the working times page (contract/status.html.twig). */
final class PlannedTimeOffExtension extends AbstractExtension
{
    public function __construct(private readonly PlannedTimeOff $plannedTimeOff)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('holiday_planned_time_off', $this->plannedSeconds(...)),
        ];
    }

    public function plannedSeconds(Year $year, \DateTimeInterface $until): int
    {
        return $this->plannedTimeOff->secondsAfter($year, $until);
    }
}
