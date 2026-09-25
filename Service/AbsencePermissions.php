<?php

namespace KimaiPlugin\HolidayBundle\Service;

use App\Entity\User;
use KimaiPlugin\HolidayBundle\Entity\Absence;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Permission rules for absences, shared by controllers and page/row action subscribers.
 *
 * "Other user" permissions only apply to users the current user may access by Kimai's own rule
 * (access_user: view_all_data, or team lead of one of the user's teams).
 */
final class AbsencePermissions
{
    public function __construct(private readonly Security $security)
    {
    }

    public function getCurrentUser(): ?User
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $user : null;
    }

    public function canAccessUser(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $user === $this->getCurrentUser() || $this->security->isGranted('access_user', $user);
    }

    public function canEditFor(?User $user): bool
    {
        return $this->check($user, 'edit_own_absence', 'edit_other_absence');
    }

    public function canEdit(Absence $absence): bool
    {
        return $this->canEditFor($absence->getUser());
    }

    public function canApproveFor(?User $user): bool
    {
        return $this->check($user, 'approve_own_absence', 'approve_other_absence');
    }

    public function canApprove(Absence $absence): bool
    {
        return $this->canApproveFor($absence->getUser());
    }

    public function canDelete(Absence $absence): bool
    {
        return $this->check($absence->getUser(), 'delete_own_absence', 'delete_other_absence');
    }

    /**
     * The ICS token is a secret of its owner: only the owner and admins (view_all_data + edit_other_absence)
     * may see or regenerate it. Team leads do not.
     */
    public function canManageIcs(User $user): bool
    {
        $current = $this->getCurrentUser();
        if ($current === null) {
            return false;
        }
        if ($user === $current) {
            return $this->security->isGranted('absence');
        }

        return $current->canSeeAllData() && $this->security->isGranted('edit_other_absence');
    }

    private function check(?User $user, string $ownPermission, string $otherPermission): bool
    {
        if ($user === null) {
            return false;
        }
        if ($user === $this->getCurrentUser()) {
            return $this->security->isGranted($ownPermission);
        }

        return $this->security->isGranted($otherPermission) && $this->canAccessUser($user);
    }
}
