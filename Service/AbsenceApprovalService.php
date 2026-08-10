<?php

namespace KimaiPlugin\HolidayBundle\Service;

use App\Entity\User;
use KimaiPlugin\HolidayBundle\Entity\Absence;
use KimaiPlugin\HolidayBundle\Enum\AbsenceStatus;
use KimaiPlugin\HolidayBundle\Repository\AbsenceRepository;
use KimaiPlugin\HolidayBundle\Repository\MonthLockRepository;

class AbsenceApprovalService
{
    public function __construct(
        private readonly AbsenceRepository $absenceRepository,
        private readonly MonthLockRepository $monthLockRepository,
        private readonly AbsenceNotifier $notifier,
        private readonly HolidayConfiguration $configuration,
        private readonly AbsenceTimesheetService $timesheetService,
    ) {
    }

    public function create(Absence $absence, User $actor): Absence
    {
        $this->assertNotLocked($absence);

        if ($this->configuration->isCommentRequired() && ($absence->getComment() === null || trim($absence->getComment()) === '')) {
            throw new \InvalidArgumentException('holiday.error.comment_required');
        }

        if ($absence->getType()->requiresApproval()) {
            $absence->setStatus(AbsenceStatus::REQUESTED);
            $this->absenceRepository->save($absence);
            $this->notifier->notifyRequested($absence);
        } else {
            $absence->setStatus(AbsenceStatus::APPROVED);
            $absence->setApprovedBy($actor);
            $absence->setApprovedAt(new \DateTimeImmutable());
            $this->absenceRepository->save($absence);
            $this->timesheetService->syncAbsence($absence);
            $this->notifier->notifyApproved($absence);
        }

        return $absence;
    }

    /**
     * Persist changes and require re-approval when the type needs approval.
     * Compensatory timesheets are cleared until approved again.
     *
     * @param \DateTimeImmutable|null $previousStart Date range before the edit (for lock checks)
     * @param \DateTimeImmutable|null $previousEnd
     */
    public function update(
        Absence $absence,
        User $actor,
        ?\DateTimeImmutable $previousStart = null,
        ?\DateTimeImmutable $previousEnd = null,
    ): Absence {
        if ($previousStart !== null && $previousEnd !== null) {
            $this->assertRangeNotLocked($absence->getUser(), $previousStart, $previousEnd);
        }
        $this->assertNotLocked($absence);

        if ($this->configuration->isCommentRequired() && ($absence->getComment() === null || trim($absence->getComment()) === '')) {
            throw new \InvalidArgumentException('holiday.error.comment_required');
        }

        $this->timesheetService->removeAbsenceTimesheets($absence);
        $absence->setApprovedBy(null);
        $absence->setApprovedAt(null);

        if ($absence->getType()->requiresApproval()) {
            $absence->setStatus(AbsenceStatus::REQUESTED);
            $this->absenceRepository->save($absence);
            $this->notifier->notifyRequested($absence);
        } else {
            $absence->setStatus(AbsenceStatus::APPROVED);
            $absence->setApprovedBy($actor);
            $absence->setApprovedAt(new \DateTimeImmutable());
            $this->absenceRepository->save($absence);
            $this->timesheetService->syncAbsence($absence);
            $this->notifier->notifyApproved($absence);
        }

        return $absence;
    }

    public function request(Absence $absence): Absence
    {
        $this->assertNotLocked($absence);
        $absence->setStatus(AbsenceStatus::REQUESTED);
        $this->absenceRepository->save($absence);
        $this->notifier->notifyRequested($absence);

        return $absence;
    }

    public function approve(Absence $absence, User $approver): Absence
    {
        $this->assertNotLocked($absence);
        $absence->setStatus(AbsenceStatus::APPROVED);
        $absence->setApprovedBy($approver);
        $absence->setApprovedAt(new \DateTimeImmutable());
        $this->absenceRepository->save($absence);
        $this->timesheetService->syncAbsence($absence);
        $this->notifier->notifyApproved($absence);

        return $absence;
    }

    public function reject(Absence $absence, User $approver): Absence
    {
        $this->assertNotLocked($absence);
        $absence->setStatus(AbsenceStatus::REJECTED);
        $absence->setApprovedBy($approver);
        $absence->setApprovedAt(new \DateTimeImmutable());
        $this->absenceRepository->save($absence);
        $this->notifier->notifyRejected($absence);

        return $absence;
    }

    public function delete(Absence $absence): void
    {
        $this->assertNotLocked($absence);
        $this->timesheetService->removeAbsenceTimesheets($absence);
        $this->absenceRepository->remove($absence);
    }

    private function assertNotLocked(Absence $absence): void
    {
        $this->assertRangeNotLocked($absence->getUser(), $absence->getStartDate(), $absence->getEndDate());
    }

    private function assertRangeNotLocked(?User $user, ?\DateTimeInterface $start, ?\DateTimeInterface $end): void
    {
        if ($user === null || $start === null || $end === null) {
            return;
        }

        $cursor = $start instanceof \DateTimeImmutable
            ? $start
            : \DateTimeImmutable::createFromInterface($start);
        $endDay = $end instanceof \DateTimeImmutable
            ? $end
            : \DateTimeImmutable::createFromInterface($end);

        while ($cursor <= $endDay) {
            if ($this->monthLockRepository->isDateLocked($user, $cursor)) {
                throw new \RuntimeException('holiday.error.month_locked');
            }
            $cursor = $cursor->modify('+1 day');
        }
    }
}
