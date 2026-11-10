<?php

namespace KimaiPlugin\HolidayBundle\Tests\Service;

use App\Entity\User;
use KimaiPlugin\HolidayBundle\Service\AbsenceNotifier;
use KimaiPlugin\HolidayBundle\Service\ApproverResolver;
use KimaiPlugin\HolidayBundle\Tests\Support\AbsenceBuilder;
use KimaiPlugin\HolidayBundle\Tests\Support\UserBuilder;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;

final class AbsenceNotifierTest extends TestCase
{
    /** @var list<Email> */
    private array $sent = [];

    public function testRequestedGoesOnlyToApprovers(): void
    {
        $user = $this->user('user@example.test');
        $a = $this->user('a@example.test');
        $b = $this->user('b@example.test');

        $this->notifier([$a, $b])->notifyRequested(AbsenceBuilder::requested($user, '2026-10-21', '2026-10-22'));

        $this->assertCount(1, $this->sent);
        $to = array_map(static fn ($address) => $address->getAddress(), $this->sent[0]->getTo());
        $this->assertSame(['a@example.test', 'b@example.test'], $to);
    }

    public function testNoApproverSendsNothing(): void
    {
        $user = $this->user('user@example.test');

        $this->notifier([])->notifyRequested(AbsenceBuilder::requested($user, '2026-10-21', '2026-10-22'));

        $this->assertSame([], $this->sent);
    }

    public function testDecisionNamesApproverAndGoesOnlyToApprovers(): void
    {
        $user = $this->user('user@example.test');
        $a = $this->user('a@example.test');
        $b = $this->user('b@example.test');
        $b->setAlias('Boss B');
        $absence = AbsenceBuilder::vacation($user, '2026-10-21', '2026-10-22')->setApprovedBy($b);

        $this->notifier([$a, $b])->notifyApproved($absence);

        $this->assertCount(1, $this->sent);
        $this->assertStringContainsString('Boss B', (string) $this->sent[0]->getTextBody());
        $to = array_map(static fn ($address) => $address->getAddress(), $this->sent[0]->getTo());
        $this->assertSame(['a@example.test', 'b@example.test'], $to);
    }

    private function user(string $email): User
    {
        $user = UserBuilder::make()->build();
        $user->setEmail($email);

        return $user;
    }

    /** @param list<User> $approvers */
    private function notifier(array $approvers): AbsenceNotifier
    {
        $mailer = $this->createStub(MailerInterface::class);
        $mailer->method('send')->willReturnCallback(function ($email): void {
            $this->sent[] = $email;
        });

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $id, array $params = []): string => $id . ' ' . implode('|', $params)
        );

        $resolver = $this->createStub(ApproverResolver::class);
        $resolver->method('approversFor')->willReturn($approvers);

        return new AbsenceNotifier($mailer, new NullLogger(), $translator, $resolver);
    }
}
