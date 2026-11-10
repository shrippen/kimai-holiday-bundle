<?php

namespace KimaiPlugin\HolidayBundle\Service;

use App\Entity\User;
use KimaiPlugin\HolidayBundle\Entity\Absence;
use KimaiPlugin\HolidayBundle\Enum\AbsenceType;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Colleagues see sickness only as "absent": the type stays with the owner and with
 * those who manage the owner (team lead of their team, admin).
 */
final class AbsenceDisclosure
{
    public const NEUTRAL_ICON = 'user';
    private const NEUTRAL_LABEL = 'holiday.absence.type.absent';
    private const PRIVATE_TYPES = [AbsenceType::SICKNESS, AbsenceType::SICKNESS_RELATIVE];

    public function __construct(private readonly AuthorizationCheckerInterface $auth)
    {
    }

    public function label(Absence $absence, User $viewer): string
    {
        return $this->showsType($absence, $viewer) ? $absence->getType()->label() : self::NEUTRAL_LABEL;
    }

    public function icon(Absence $absence, User $viewer): string
    {
        return $this->showsType($absence, $viewer) ? $absence->getType()->icon() : self::NEUTRAL_ICON;
    }

    private function showsType(Absence $absence, User $viewer): bool
    {
        if (!\in_array($absence->getType(), self::PRIVATE_TYPES, true)) {
            return true;
        }

        $owner = $absence->getUser();
        if ($owner === null) {
            return false;
        }
        if ($owner === $viewer || ($owner->getId() !== null && $owner->getId() === $viewer->getId())) {
            return true;
        }

        return $this->auth->isGranted('view_other_absence') && $this->auth->isGranted('access_user', $owner);
    }
}
