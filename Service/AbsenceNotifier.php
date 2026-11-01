<?php

namespace KimaiPlugin\HolidayBundle\Service;

use App\Entity\User;
use KimaiPlugin\HolidayBundle\Entity\Absence;
use KimaiPlugin\HolidayBundle\Entity\WorkdayShift;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;

class AbsenceNotifier
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly TranslatorInterface $translator,
        private readonly ApproverResolver $approverResolver,
    ) {
    }

    public function notifyRequested(Absence $absence): void
    {
        $user = $absence->getUser();
        if ($user === null) {
            return;
        }

        $this->send(
            $this->recipients($user),
            $this->translator->trans('holiday.mail.requested.subject'),
            $this->translator->trans('holiday.mail.requested.body', [
                '%user%' => $user->getDisplayName(),
                '%type%' => $this->translator->trans($absence->getType()->label()),
                '%start%' => $absence->getStartDate()?->format('Y-m-d') ?? '',
                '%end%' => $absence->getEndDate()?->format('Y-m-d') ?? '',
                '%comment%' => $absence->getComment() ?? '',
            ])
        );
    }

    public function notifyApproved(Absence $absence): void
    {
        $user = $absence->getUser();
        if ($user === null) {
            return;
        }

        $this->send(
            $this->recipients($user),
            $this->translator->trans('holiday.mail.approved.subject'),
            $this->translator->trans('holiday.mail.approved.body', [
                '%approver%' => $absence->getApprovedBy()?->getDisplayName() ?? '',
                '%user%' => $user->getDisplayName(),
                '%type%' => $this->translator->trans($absence->getType()->label()),
                '%start%' => $absence->getStartDate()?->format('Y-m-d') ?? '',
                '%end%' => $absence->getEndDate()?->format('Y-m-d') ?? '',
            ])
        );
    }

    public function notifyRejected(Absence $absence): void
    {
        $user = $absence->getUser();
        if ($user === null) {
            return;
        }

        $this->send(
            $this->recipients($user),
            $this->translator->trans('holiday.mail.rejected.subject'),
            $this->translator->trans('holiday.mail.rejected.body', [
                '%approver%' => $absence->getApprovedBy()?->getDisplayName() ?? '',
                '%user%' => $user->getDisplayName(),
                '%type%' => $this->translator->trans($absence->getType()->label()),
                '%start%' => $absence->getStartDate()?->format('Y-m-d') ?? '',
                '%end%' => $absence->getEndDate()?->format('Y-m-d') ?? '',
            ])
        );
    }

    public function notifyShiftRequested(WorkdayShift $shift): void
    {
        $this->notifyShift($shift, 'requested');
    }

    public function notifyShiftApproved(WorkdayShift $shift): void
    {
        $this->notifyShift($shift, 'approved');
    }

    public function notifyShiftRejected(WorkdayShift $shift): void
    {
        $this->notifyShift($shift, 'rejected');
    }

    /** An approved shift was deleted: requester and approvers learn who took it back. */
    public function notifyShiftWithdrawn(WorkdayShift $shift, User $actor): void
    {
        $this->notifyShift($shift, 'withdrawn', $actor);
    }

    private function notifyShift(WorkdayShift $shift, string $event, ?User $actor = null): void
    {
        $user = $shift->getUser();
        if ($user === null) {
            return;
        }

        $this->send(
            $this->recipients($user, WorkdayShiftService::PERMISSION),
            $this->translator->trans('holiday.mail.shift_' . $event . '.subject'),
            $this->translator->trans('holiday.mail.shift_' . $event . '.body', [
                '%user%' => $user->getDisplayName(),
                '%from%' => $shift->getFromDate()?->format('Y-m-d') ?? '',
                '%to%' => $shift->getToDate()?->format('Y-m-d') ?? '',
                '%comment%' => $shift->getComment() ?? '',
                '%approver%' => ($actor ?? $shift->getApprovedBy())?->getDisplayName() ?? '',
            ])
        );
    }

    /**
     * The requester and every account that could approve the request.
     *
     * @return list<string|null>
     */
    private function recipients(User $user, string $permission = ApproverAccess::PERMISSION): array
    {
        return [
            $user->getEmail(),
            ...array_map(static fn (User $approver): ?string => $approver->getEmail(), $this->approverResolver->approversFor($user, $permission)),
        ];
    }

    /**
     * @param list<string|null> $recipients
     */
    private function send(array $recipients, string $subject, string $body): void
    {
        $emails = array_values(array_unique(array_filter($recipients)));
        if ($emails === []) {
            return;
        }

        try {
            $email = (new Email())
                ->to(...$emails)
                ->subject($subject)
                ->text($body);
            $this->mailer->send($email);
        } catch (\Throwable $e) {
            $this->logger->warning('HolidayBundle: failed to send absence email: ' . $e->getMessage());
        }
    }
}
