<?php

namespace KimaiPlugin\HolidayBundle\Service;

use App\Entity\User;
use App\Repository\UserRepository;

/**
 * Accounts that may approve the absences of a user, and the inverse: users an approver is responsible for.
 * Nobody is their own approver.
 */
class ApproverResolver
{
    /** @var list<User>|null */
    private ?array $enabledUsers = null;

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly ApproverAccess $access,
    ) {
    }

    /**
     * @return list<User>
     */
    public function approversFor(User $subject, string $permission = ApproverAccess::PERMISSION): array
    {
        $approvers = [];
        foreach ($this->enabledUsers() as $candidate) {
            if ($this->isSame($candidate, $subject)) {
                continue;
            }
            if ($this->access->mayApprove($candidate, $permission) && $this->access->mayAccess($subject, $candidate)) {
                $approvers[] = $candidate;
            }
        }

        return $approvers;
    }

    /**
     * @return list<User>
     */
    public function subjectsFor(User $approver, string $permission = ApproverAccess::PERMISSION): array
    {
        if (!$this->access->mayApprove($approver, $permission)) {
            return [];
        }

        $subjects = [];
        foreach ($this->enabledUsers() as $subject) {
            if (!$this->isSame($subject, $approver) && $this->access->mayAccess($subject, $approver)) {
                $subjects[] = $subject;
            }
        }

        return $subjects;
    }

    public function isApproverOf(User $actor, User $subject, string $permission = ApproverAccess::PERMISSION): bool
    {
        foreach ($this->approversFor($subject, $permission) as $approver) {
            if ($this->isSame($approver, $actor)) {
                return true;
            }
        }

        return false;
    }

    public function isSame(User $a, User $b): bool
    {
        if ($a === $b) {
            return true;
        }

        return $a->getId() !== null && $a->getId() === $b->getId();
    }

    /**
     * @return list<User>
     */
    private function enabledUsers(): array
    {
        return $this->enabledUsers ??= array_values(array_filter(
            $this->userRepository->findBy(['enabled' => true]),
            static fn (User $user): bool => $user->isEnabled()
        ));
    }
}
