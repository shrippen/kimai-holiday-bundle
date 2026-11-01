<?php

namespace KimaiPlugin\HolidayBundle\Service;

use App\Entity\User;
use KimaiPlugin\HolidayBundle\Entity\WorkdayShift;
use KimaiPlugin\HolidayBundle\Enum\AbsenceStatus;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Permission rules for workday shifts. "Other user" rights only apply to users the current user
 * may access by Kimai's own rule (access_user).
 */
final class WorkdayShiftPermissions
{
    public function __construct(private readonly Security $security)
    {
    }

    public function getCurrentUser(): ?User
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $user : null;
    }

    public function canCreateFor(?User $user): bool
    {
        if ($user === null) {
            return false;
        }
        if ($user === $this->getCurrentUser()) {
            return $this->security->isGranted('shift_own');
        }

        return $this->security->isGranted('shift_other') && $this->canAccessUser($user);
    }

    public function canApproveFor(?User $user): bool
    {
        // nobody approves their own shift, admins included
        if ($user === null || $user === $this->getCurrentUser()) {
            return false;
        }

        return $this->security->isGranted('approve_shift') && $this->canAccessUser($user);
    }

    public function canApprove(WorkdayShift $shift): bool
    {
        return $this->canApproveFor($shift->getUser());
    }

    public function canDelete(WorkdayShift $shift): bool
    {
        $user = $shift->getUser();
        if ($user === null) {
            return false;
        }
        if ($user === $this->getCurrentUser() && $shift->getStatus() === AbsenceStatus::REQUESTED && $this->security->isGranted('shift_own')) {
            return true;
        }
        if ($user !== $this->getCurrentUser() && $this->security->isGranted('shift_other') && $this->canAccessUser($user)) {
            return true;
        }

        return $this->canApproveFor($user);
    }

    private function canAccessUser(User $user): bool
    {
        return $user === $this->getCurrentUser() || $this->security->isGranted('access_user', $user);
    }
}
