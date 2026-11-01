<?php

namespace KimaiPlugin\HolidayBundle\Tests\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use KimaiPlugin\HolidayBundle\Service\ApproverAccess;
use KimaiPlugin\HolidayBundle\Service\ApproverResolver;
use KimaiPlugin\HolidayBundle\Tests\Support\UserBuilder;
use PHPUnit\Framework\TestCase;

final class ApproverResolverTest extends TestCase
{
    public function testBothAdminsAreApproversOfEmployee(): void
    {
        [$a, $b, $employee] = $this->users();

        $this->assertSame([$a, $b], $this->resolver([$a, $b, $employee], [$a, $b])->approversFor($employee));
    }

    public function testSubjectIsNeverOwnApprover(): void
    {
        [$a, $b] = $this->users();

        $this->assertSame([$b], $this->resolver([$a, $b], [$a, $b])->approversFor($a));
    }

    public function testDisabledUserIsNotApprover(): void
    {
        [$a, $b, $employee] = $this->users();
        $b->setEnabled(false);

        $this->assertSame([$a], $this->resolver([$a, $b, $employee], [$a, $b])->approversFor($employee));
    }

    public function testSubjectsOfApproverExcludeSelf(): void
    {
        [$a, $b, $employee] = $this->users();

        $this->assertSame([$b, $employee], $this->resolver([$a, $b, $employee], [$a, $b])->subjectsFor($a));
    }

    public function testIsApproverOfExcludesSelf(): void
    {
        [$admin, , $employee] = $this->users();

        $this->assertFalse($this->resolver([$admin, $employee], [$admin])->isApproverOf($admin, $admin));
    }

    public function testIsApproverOfAcceptsOtherApprover(): void
    {
        [$admin, , $employee] = $this->users();

        $this->assertTrue($this->resolver([$admin, $employee], [$admin])->isApproverOf($admin, $employee));
    }

    public function testApproveShiftOnlyCandidateIsApproverForThatPermissionOnly(): void
    {
        [$lead, $employee] = $this->users();
        $resolver = $this->resolver([$lead, $employee], [], [$lead]);

        $this->assertSame([$lead], $resolver->approversFor($employee, 'approve_shift'));
        $this->assertSame([], $resolver->approversFor($employee));
        $this->assertTrue($resolver->isApproverOf($lead, $employee, 'approve_shift'));
        $this->assertFalse($resolver->isApproverOf($lead, $employee));
        $this->assertSame([$employee], $resolver->subjectsFor($lead, 'approve_shift'));
        $this->assertSame([], $resolver->subjectsFor($lead));
    }

    public function testSubjectIsNeverOwnShiftApprover(): void
    {
        [$lead] = $this->users();
        $resolver = $this->resolver([$lead], [], [$lead]);

        $this->assertSame([], $resolver->approversFor($lead, 'approve_shift'));
        $this->assertFalse($resolver->isApproverOf($lead, $lead, 'approve_shift'));
    }

    public function testIsSameMatchesDistinctObjectsWithSameId(): void
    {
        [$a, $b, $c] = $this->users();
        foreach ([$a, $b] as $user) {
            $id = new \ReflectionProperty(User::class, 'id');
            $id->setValue($user, 7);
        }
        $resolver = $this->resolver([], []);

        $this->assertTrue($resolver->isSame($a, $b));
        $this->assertFalse($resolver->isSame($a, $c));
    }

    /** @return list<User> */
    private function users(): array
    {
        return [UserBuilder::make()->build(), UserBuilder::make()->build(), UserBuilder::make()->build()];
    }

    /**
     * @param list<User> $enabled all users the repository returns
     * @param list<User> $approvers users with the absence approval permission (they access everyone)
     * @param list<User> $shiftApprovers users with the shift approval permission
     */
    private function resolver(array $enabled, array $approvers, array $shiftApprovers = []): ApproverResolver
    {
        $repository = $this->createStub(UserRepository::class);
        $repository->method('findBy')->willReturn($enabled);

        $access = $this->createStub(ApproverAccess::class);
        $access->method('mayApprove')->willReturnCallback(static fn (User $u, string $permission = ApproverAccess::PERMISSION): bool => \in_array(
            $u,
            $permission === ApproverAccess::PERMISSION ? $approvers : $shiftApprovers,
            true
        ));
        $access->method('mayAccess')->willReturn(true);

        return new ApproverResolver($repository, $access);
    }
}
