<?php

namespace KimaiPlugin\HolidayBundle\Service;

use App\Entity\User;
use KimaiPlugin\HolidayBundle\Entity\Absence;
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
    ) {
    }

    public function notifyRequested(Absence $absence): void
    {
        $user = $absence->getUser();
        if ($user === null) {
            return;
        }

        $supervisor = method_exists($user, 'getSupervisor') ? $user->getSupervisor() : null;
        $recipients = array_filter([
            $user->getEmail(),
            $supervisor instanceof User ? $supervisor->getEmail() : null,
        ]);

        $this->send(
            $recipients,
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
        if ($user === null || $user->getEmail() === null) {
            return;
        }

        $this->send(
            [$user->getEmail()],
            $this->translator->trans('holiday.mail.approved.subject'),
            $this->translator->trans('holiday.mail.approved.body', [
                '%type%' => $this->translator->trans($absence->getType()->label()),
                '%start%' => $absence->getStartDate()?->format('Y-m-d') ?? '',
                '%end%' => $absence->getEndDate()?->format('Y-m-d') ?? '',
            ])
        );
    }

    public function notifyRejected(Absence $absence): void
    {
        $user = $absence->getUser();
        if ($user === null || $user->getEmail() === null) {
            return;
        }

        $this->send(
            [$user->getEmail()],
            $this->translator->trans('holiday.mail.rejected.subject'),
            $this->translator->trans('holiday.mail.rejected.body', [
                '%type%' => $this->translator->trans($absence->getType()->label()),
                '%start%' => $absence->getStartDate()?->format('Y-m-d') ?? '',
                '%end%' => $absence->getEndDate()?->format('Y-m-d') ?? '',
            ])
        );
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
