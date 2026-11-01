<?php

namespace KimaiPlugin\HolidayBundle\Service;

use App\Entity\User;
use KimaiPlugin\HolidayBundle\Entity\Absence;
use KimaiPlugin\HolidayBundle\Enum\AbsenceStatus;
use KimaiPlugin\HolidayBundle\Enum\AbsenceType;
use KimaiPlugin\HolidayBundle\Repository\AbsenceRepository;

class AbsenceApprovalService
{
    /** Longest allowed absence (inclusive days). */
    public const MAX_DAYS = 366;
    /** Upper bound for the hours of an OTHER absence per day. */
    public const MAX_DURATION = 86400;

    public function __construct(
        private readonly AbsenceRepository $absenceRepository,
        private readonly AbsenceNotifier $notifier,
        private readonly HolidayConfiguration $configuration,
        private readonly AbsenceTimesheetService $timesheetService,
        private readonly PeriodLockGuard $periodLockGuard,
        private readonly ApproverResolver $approverResolver,
    ) {
    }

    /**
     * Validates dates, duration and overlaps. Throws \InvalidArgumentException with a
     * translation key (flashmessages domain).
     */
    public function validate(Absence $absence): void
    {
        $start = $absence->getStartDate();
        $end = $absence->getEndDate();
        if ($absence->getUser() === null || $start === null || $end === null) {
            throw new \InvalidArgumentException('holiday.error.invalid_date');
        }

        if ($end->format('Y-m-d') < $start->format('Y-m-d')) {
            throw new \InvalidArgumentException('holiday.error.end_before_start');
        }

        $startDay = new \DateTimeImmutable($start->format('Y-m-d'));
        $endDay = new \DateTimeImmutable($end->format('Y-m-d'));
        if ((int) $startDay->diff($endDay)->days + 1 > self::MAX_DAYS) {
            throw new \InvalidArgumentException('holiday.error.range_too_long');
        }

        $duration = $absence->getDuration();
        if ($duration !== null && ($duration < 0 || $duration > self::MAX_DURATION)) {
            throw new \InvalidArgumentException('holiday.error.invalid_duration');
        }

        if ($absence->isHalfDay() && $absence->getType() === AbsenceType::VACATION && !$this->configuration->allowHalfDayVacation()) {
            throw new \InvalidArgumentException('holiday.error.half_day_not_allowed');
        }

        if ($this->absenceRepository->findOverlapping($absence->getUser(), $startDay, $endDay, $absence->getId()) !== []) {
            throw new \InvalidArgumentException('holiday.error.overlap');
        }
    }

    public function create(Absence $absence, User $actor): Absence
    {
        $this->validate($absence);
        $this->assertNotLocked($absence);

        if ($this->configuration->isCommentRequired() && ($absence->getComment() === null || trim($absence->getComment()) === '')) {
            throw new \InvalidArgumentException('holiday.error.comment_required');
        }

        // An approver entering an absence for someone else (e.g. retroactive entry) decides it right away.
        if ($absence->getType()->requiresApproval() && !$this->isApprovedByActor($absence, $actor)) {
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
     * Persist changes and require re-approval when the type needs approval, unless an approver edits
     * someone else's absence.
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
            $previousUser = $absence->getUser();
            if ($previousUser !== null) {
                $this->periodLockGuard->assertOpen($previousUser, $previousStart, $previousEnd);
            }
        }
        $this->validate($absence);
        $this->assertNotLocked($absence);

        if ($this->configuration->isCommentRequired() && ($absence->getComment() === null || trim($absence->getComment()) === '')) {
            throw new \InvalidArgumentException('holiday.error.comment_required');
        }

        $this->timesheetService->removeAbsenceTimesheets($absence);
        $absence->setApprovedBy(null);
        $absence->setApprovedAt(null);

        // an approver editing someone else's absence decides it right away (as in create())
        if ($absence->getType()->requiresApproval() && !$this->isApprovedByActor($absence, $actor)) {
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
     * @param bool $notify false when an approver only undoes their own decision
     */
    public function request(Absence $absence, bool $notify = true): Absence
    {
        $this->assertNotLocked($absence);
        $this->timesheetService->removeAbsenceTimesheets($absence);
        $absence->setStatus(AbsenceStatus::REQUESTED);
        $absence->setApprovedBy(null);
        $absence->setApprovedAt(null);
        $this->absenceRepository->save($absence);
        if ($notify) {
            $this->notifier->notifyRequested($absence);
        }

        return $absence;
    }

    public function approve(Absence $absence, User $approver): Absence
    {
        $this->assertNotOwn($absence, $approver);
        $this->assertRequested($absence);
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
        $this->assertNotOwn($absence, $approver);
        $this->assertRequested($absence);
        $this->assertNotLocked($absence);
        $this->timesheetService->removeAbsenceTimesheets($absence);
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

    private function isApprovedByActor(Absence $absence, User $actor): bool
    {
        $user = $absence->getUser();

        return $user !== null && $this->approverResolver->isApproverOf($actor, $user);
    }

    private function assertNotOwn(Absence $absence, User $approver): void
    {
        $user = $absence->getUser();
        if ($user !== null && $this->approverResolver->isSame($user, $approver)) {
            throw new \InvalidArgumentException('holiday.error.self_approval');
        }
    }

    private function assertRequested(Absence $absence): void
    {
        if ($absence->getStatus() !== AbsenceStatus::REQUESTED) {
            throw new \InvalidArgumentException('holiday.error.not_requested');
        }
    }

    private function assertNotLocked(Absence $absence): void
    {
        $user = $absence->getUser();
        $start = $absence->getStartDate();
        $end = $absence->getEndDate();
        if ($user === null || $start === null || $end === null) {
            return;
        }

        $this->periodLockGuard->assertOpen($user, $start, $end);
    }
}
