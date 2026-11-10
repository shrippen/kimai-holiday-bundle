<?php

namespace KimaiPlugin\HolidayBundle\Service;

use App\Entity\Team;
use App\Entity\User;
use App\Repository\TeamRepository;
use App\Repository\UserRepository;

/**
 * People and teams of the absence calendar. Everyone sees the same calendar:
 * all active users, filterable by any team.
 */
final class CalendarMembers
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly TeamRepository $teamRepository,
    ) {
    }

    /**
     * @return Team[]
     */
    public function teams(): array
    {
        return $this->teamRepository->findAll();
    }

    /**
     * Active users of the team, or all active users without a team.
     *
     * @return User[]
     */
    public function users(?Team $team): array
    {
        $users = $team !== null ? $team->getUsers() : $this->userRepository->findAll();

        return array_values(array_filter($users, static fn (User $u): bool => $u->isEnabled()));
    }
}
