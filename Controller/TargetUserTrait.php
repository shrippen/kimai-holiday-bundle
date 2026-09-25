<?php

namespace KimaiPlugin\HolidayBundle\Controller;

use App\Controller\AbstractController;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

trait TargetUserTrait
{
    protected function getTargetUser(Request $request, UserRepository $userRepository): User
    {
        /** @var User $current */
        $current = $this->getUser();
        $userId = $request->query->getInt('user');

        if ($userId <= 0) {
            return $current;
        }

        if ($userId === $current->getId()) {
            return $current;
        }

        if (!$this->isGranted('hours_other_profile') && !$this->isGranted('view_other_absence') && !$this->isGranted('edit_other_absence')) {
            throw new AccessDeniedException('You cannot view other users.');
        }

        $user = $userRepository->find($userId);
        if (!$user instanceof User) {
            throw $this->createNotFoundException('User not found');
        }

        $this->assertCanAccessUser($user);

        return $user;
    }

    /**
     * Scope for "other user" permissions: Kimai's own user access rule (same user, users with
     * "view_all_data" (admins by default), or team leads of a team the user belongs to).
     */
    protected function canAccessUser(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $user === $this->getUser() || $this->isGranted('access_user', $user);
    }

    protected function assertCanAccessUser(?User $user): void
    {
        if (!$this->canAccessUser($user)) {
            throw new AccessDeniedException('You cannot access this user.');
        }
    }
}
