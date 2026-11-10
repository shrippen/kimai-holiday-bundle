<?php

namespace KimaiPlugin\HolidayBundle\Twig;

use App\Entity\User;
use KimaiPlugin\HolidayBundle\Entity\Absence;
use KimaiPlugin\HolidayBundle\Service\AbsenceDisclosure;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/** Absence type label as the viewer may see it (sickness of colleagues shows as "absent"). */
final class AbsenceDisclosureExtension extends AbstractExtension
{
    public function __construct(private readonly AbsenceDisclosure $disclosure)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('holiday_absence_label', $this->label(...)),
        ];
    }

    public function label(Absence $absence, User $viewer): string
    {
        return $this->disclosure->label($absence, $viewer);
    }
}
