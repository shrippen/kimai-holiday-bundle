<?php

namespace KimaiPlugin\HolidayBundle\Tests\Service;

use App\Entity\Team;
use App\Repository\TeamRepository;
use App\Repository\UserRepository;
use KimaiPlugin\HolidayBundle\Service\CalendarMembers;
use KimaiPlugin\HolidayBundle\Tests\Support\UserBuilder;
use PHPUnit\Framework\TestCase;

final class CalendarMembersTest extends TestCase
{
    public function testWithoutTeamListsAllActiveUsers(): void
    {
        $anna = UserBuilder::make()->build();
        $ben = UserBuilder::make()->build();
        $gone = UserBuilder::make()->build();
        $gone->setEnabled(false);

        $members = $this->members([$anna, $ben, $gone], []);

        self::assertSame([$anna, $ben], $members->users(null));
    }

    public function testTeamListsItsActiveMembersOnly(): void
    {
        $anna = UserBuilder::make()->build();
        $ben = UserBuilder::make()->build();
        $gone = UserBuilder::make()->build();
        $gone->setEnabled(false);
        $team = new Team('Studio');
        $team->addUser($ben);
        $team->addUser($gone);

        $members = $this->members([$anna, $ben, $gone], [$team]);

        self::assertSame([$ben], $members->users($team));
    }

    public function testEveryTeamIsSelectable(): void
    {
        $studio = new Team('Studio');
        $office = new Team('Büro');

        self::assertSame([$studio, $office], $this->members([], [$studio, $office])->teams());
    }

    private function members(array $users, array $teams): CalendarMembers
    {
        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('findAll')->willReturn($users);
        $teamRepository = $this->createStub(TeamRepository::class);
        $teamRepository->method('findAll')->willReturn($teams);

        return new CalendarMembers($userRepository, $teamRepository);
    }
}
