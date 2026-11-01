<?php

namespace KimaiPlugin\HolidayBundle\Tests\Service;

use App\Entity\User;
use KimaiPlugin\HolidayBundle\Service\WorkdayShiftPermissions;
use KimaiPlugin\HolidayBundle\Tests\Support\UserBuilder;
use KimaiPlugin\HolidayBundle\Tests\Support\WorkdayShiftBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

final class WorkdayShiftPermissionsTest extends TestCase
{
    public function testOwnAllowedWithShiftOwn(): void
    {
        $me = UserBuilder::make()->build();

        self::assertTrue($this->permissions($me, ['shift_own'])->canCreateFor($me));
        self::assertFalse($this->permissions($me, [])->canCreateFor($me));
    }

    public function testOtherDeniedWithoutShiftOther(): void
    {
        $me = UserBuilder::make()->build();
        $other = UserBuilder::make()->build();

        self::assertFalse($this->permissions($me, ['shift_own', 'access_user'])->canCreateFor($other));
        self::assertTrue($this->permissions($me, ['shift_other', 'access_user'])->canCreateFor($other));
        self::assertFalse($this->permissions($me, ['shift_other'])->canCreateFor($other));
    }

    public function testApproveNeverSelf(): void
    {
        $me = UserBuilder::make()->build();
        $other = UserBuilder::make()->build();
        $permissions = $this->permissions($me, ['approve_shift', 'access_user']);

        self::assertFalse($permissions->canApproveFor($me));
        self::assertTrue($permissions->canApproveFor($other));
        self::assertTrue($permissions->canApprove(WorkdayShiftBuilder::requested($other, '2026-10-30', '2026-10-29')));
        self::assertFalse($this->permissions($me, ['access_user'])->canApproveFor($other));
    }

    public function testDelete(): void
    {
        $me = UserBuilder::make()->build();
        $other = UserBuilder::make()->build();

        self::assertTrue($this->permissions($me, ['shift_own'])->canDelete(WorkdayShiftBuilder::requested($me, '2026-10-30', '2026-10-29')));
        self::assertFalse($this->permissions($me, ['shift_own'])->canDelete(WorkdayShiftBuilder::approved($me, '2026-10-30', '2026-10-29')));
        self::assertTrue($this->permissions($me, ['shift_other', 'access_user'])->canDelete(WorkdayShiftBuilder::approved($other, '2026-10-30', '2026-10-29')));
        self::assertTrue($this->permissions($me, ['approve_shift', 'access_user'])->canDelete(WorkdayShiftBuilder::approved($other, '2026-10-30', '2026-10-29')));
        self::assertFalse($this->permissions($me, [])->canDelete(WorkdayShiftBuilder::requested($other, '2026-10-30', '2026-10-29')));
    }

    /**
     * @param list<string> $granted
     */
    private function permissions(User $current, array $granted): WorkdayShiftPermissions
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($current);
        $security->method('isGranted')->willReturnCallback(static fn (string $attr): bool => \in_array($attr, $granted, true));

        return new WorkdayShiftPermissions($security);
    }
}
