<?php

namespace KimaiPlugin\HolidayBundle\Service;

use App\Entity\User;
use App\Security\RolePermissionManager;

/**
 * Thin seam over Kimai's final RolePermissionManager: who may approve whose absences.
 */
class ApproverAccess
{
    public const PERMISSION = 'approve_other_absence';

    public function __construct(private readonly RolePermissionManager $permissions)
    {
    }

    public function mayApprove(User $candidate, string $permission = self::PERMISSION): bool
    {
        return $this->permissions->hasRolePermission($candidate, $permission);
    }

    public function mayAccess(User $subject, User $candidate): bool
    {
        // Kimai's checkUserAccess() only honours "view all data" through a flag it sets for the logged-in
        // user; a ROLE_ADMIN candidate who is not the session user would fall through to the team rules.
        if ($this->permissions->hasRolePermission($candidate, 'view_all_data')) {
            return true;
        }

        return $this->permissions->checkUserAccess($subject, $candidate);
    }
}
