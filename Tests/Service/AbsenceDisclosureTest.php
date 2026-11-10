<?php

namespace KimaiPlugin\HolidayBundle\Tests\Service;

use App\Entity\User;
use KimaiPlugin\HolidayBundle\Enum\AbsenceType;
use KimaiPlugin\HolidayBundle\Service\AbsenceDisclosure;
use KimaiPlugin\HolidayBundle\Tests\Support\AbsenceBuilder;
use KimaiPlugin\HolidayBundle\Tests\Support\UserBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class AbsenceDisclosureTest extends TestCase
{
    public function testColleagueSeesSicknessAsAbsent(): void
    {
        $owner = UserBuilder::make()->build();
        $sick = AbsenceBuilder::vacation($owner, '2026-11-12', '2026-11-12')->setType(AbsenceType::SICKNESS);

        $disclosure = $this->disclosure(manages: false);

        self::assertSame('holiday.absence.type.absent', $disclosure->label($sick, UserBuilder::make()->build()));
        self::assertSame(AbsenceDisclosure::NEUTRAL_ICON, $disclosure->icon($sick, UserBuilder::make()->build()));
    }

    public function testSicknessOfARelativeIsHiddenToo(): void
    {
        $owner = UserBuilder::make()->build();
        $sick = AbsenceBuilder::vacation($owner, '2026-11-12', '2026-11-12')->setType(AbsenceType::SICKNESS_RELATIVE);

        self::assertSame('holiday.absence.type.absent', $this->disclosure(manages: false)->label($sick, UserBuilder::make()->build()));
    }

    public function testOwnerSeesOwnSickness(): void
    {
        $owner = UserBuilder::make()->build();
        $sick = AbsenceBuilder::vacation($owner, '2026-11-12', '2026-11-12')->setType(AbsenceType::SICKNESS);

        self::assertSame(AbsenceType::SICKNESS->label(), $this->disclosure(manages: false)->label($sick, $owner));
    }

    public function testManagerSeesSickness(): void
    {
        $owner = UserBuilder::make()->build();
        $sick = AbsenceBuilder::vacation($owner, '2026-11-12', '2026-11-12')->setType(AbsenceType::SICKNESS);

        self::assertSame(AbsenceType::SICKNESS->label(), $this->disclosure(manages: true)->label($sick, UserBuilder::make()->build()));
    }

    public function testColleagueSeesVacation(): void
    {
        $owner = UserBuilder::make()->build();
        $vacation = AbsenceBuilder::vacation($owner, '2026-11-12', '2026-11-12');

        $disclosure = $this->disclosure(manages: false);

        self::assertSame(AbsenceType::VACATION->label(), $disclosure->label($vacation, UserBuilder::make()->build()));
        self::assertSame(AbsenceType::VACATION->icon(), $disclosure->icon($vacation, UserBuilder::make()->build()));
    }

    private function disclosure(bool $manages): AbsenceDisclosure
    {
        $auth = $this->createStub(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->willReturnCallback(
            static fn (string $attribute, mixed $subject = null): bool => $manages
                && ($attribute === 'view_other_absence' || ($attribute === 'access_user' && $subject instanceof User))
        );

        return new AbsenceDisclosure($auth);
    }
}
