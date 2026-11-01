<?php

namespace KimaiPlugin\HolidayBundle\Service;

use App\Entity\User;
use KimaiPlugin\HolidayBundle\Entity\WorkdayShift;
use KimaiPlugin\HolidayBundle\Enum\AbsenceStatus;
use KimaiPlugin\HolidayBundle\Repository\AbsenceRepository;
use KimaiPlugin\HolidayBundle\Repository\ExtraShiftRepository;
use KimaiPlugin\HolidayBundle\Repository\LocationUserRepository;
use KimaiPlugin\HolidayBundle\Repository\WorkdayShiftRepository;

/** Request, validation and decision of workday shifts (F = origin day, T = target day). */
class WorkdayShiftService
{
    public const PERMISSION = 'approve_shift';

    public function __construct(
        private readonly WorkdayShiftRepository $repository,
        private readonly UserWorkContract $contract,
        private readonly AbsenceWorkdayHelper $workdayHelper,
        private readonly LocationUserRepository $locationUsers,
        private readonly LocationSchedule $schedule,
        private readonly AbsenceRepository $absenceRepository,
        private readonly ExtraShiftRepository $extraShiftRepository,
        private readonly PeriodLockGuard $periodLockGuard,
        private readonly ApproverResolver $approverResolver,
        private readonly AbsenceNotifier $notifier,
        private readonly WorkdayShiftOverrides $overrides,
    ) {
    }

    /**
     * Throws \InvalidArgumentException with a flashmessages key; the first violation wins.
     */
    public function validate(WorkdayShift $shift): void
    {
        $user = $shift->getUser();
        $from = $shift->getFromDate();
        $to = $shift->getToDate();
        if ($user === null || $from === null || $to === null) {
            throw new \InvalidArgumentException('holiday.error.invalid_date');
        }

        $fromYmd = $from->format('Y-m-d');
        $toYmd = $to->format('Y-m-d');
        if ($fromYmd === $toYmd) {
            throw $this->error('shift_same_day');
        }
        if ($from->format('o-W') !== $to->format('o-W')) {
            throw $this->error('shift_not_same_week');
        }

        $id = $shift->getId();
        if ($this->contract->getContractSecondsForDate($user, $from) === 0 || $this->workdayHelper->isFullDayPublicHoliday($user, $from)) {
            throw $this->error('shift_from_not_workday');
        }
        if ($this->repository->findActiveTouching($user, $from, $id) !== []) {
            throw $this->error('shift_from_taken');
        }
        if ($this->contract->getContractSecondsForDate($user, $to) > 0) {
            throw $this->error('shift_to_workday');
        }
        if ($this->workdayHelper->isFullDayPublicHoliday($user, $to)) {
            throw $this->error('shift_to_holiday');
        }
        if ($this->isOutsideContract($user, $toYmd)) {
            throw $this->error('shift_to_outside_contract');
        }
        if ($this->repository->findActiveTouching($user, $to, $id) !== []) {
            throw $this->error('shift_to_taken');
        }

        $location = $this->locationUsers->findLocationOf($user);
        if ($location === null) {
            throw $this->error('shift_no_location');
        }
        if (!$this->schedule->slotFor($location, $to)->open) {
            throw $this->error('shift_to_closed');
        }
        if ($this->hasApprovedAbsence($user, $from) || $this->hasApprovedAbsence($user, $to)) {
            throw $this->error('shift_absent');
        }
        if ($this->extraShiftRepository->findOneByUserAndDate($user, $to) !== null) {
            throw $this->error('shift_target_extra');
        }
    }

    public function create(WorkdayShift $shift, User $actor): WorkdayShift
    {
        $this->validate($shift);
        $this->assertNotLocked($shift);

        $user = $shift->getUser();
        if ($user !== null && $this->approverResolver->isApproverOf($actor, $user, self::PERMISSION)) {
            $this->markApproved($shift, $actor);
            $this->repository->save($shift);
            $this->overrides->reset($user);
            $this->notifier->notifyShiftApproved($shift);

            return $shift;
        }

        $shift->setStatus(AbsenceStatus::REQUESTED);
        $this->repository->save($shift);
        $this->overrides->reset($user);
        $this->notifier->notifyShiftRequested($shift);

        return $shift;
    }

    public function approve(WorkdayShift $shift, User $approver): WorkdayShift
    {
        $this->assertNotOwn($shift, $approver);
        $this->assertRequested($shift);
        // the situation may have changed since the request (extra shift, absence, other shift)
        $this->validate($shift);
        $this->assertNotLocked($shift);

        $this->markApproved($shift, $approver);
        $this->repository->save($shift);
        $this->overrides->reset($shift->getUser());
        $this->notifier->notifyShiftApproved($shift);

        return $shift;
    }

    public function reject(WorkdayShift $shift, User $approver): WorkdayShift
    {
        $this->assertNotOwn($shift, $approver);
        $this->assertRequested($shift);
        $this->assertNotLocked($shift);

        $shift->setStatus(AbsenceStatus::REJECTED);
        $shift->setApprovedBy($approver);
        $shift->setApprovedAt(new \DateTimeImmutable());
        $this->repository->save($shift);
        $this->overrides->reset($shift->getUser());
        $this->notifier->notifyShiftRejected($shift);

        return $shift;
    }

    /**
     * Back to REQUESTED (an approver undoes an approval). Only approved shifts.
     *
     * @param bool $notify false when an approver only undoes their own decision
     */
    public function request(WorkdayShift $shift, bool $notify = true): WorkdayShift
    {
        if ($shift->getStatus() !== AbsenceStatus::APPROVED) {
            throw $this->error('shift_not_approved');
        }
        $this->assertNoAbsenceOnTarget($shift);
        $this->assertNotLocked($shift);

        $this->backToRequested($shift);
        if ($notify) {
            $this->notifier->notifyShiftRequested($shift);
        }

        return $shift;
    }

    /**
     * Undo of a decision: approved or rejected back to REQUESTED. A rejected shift is validated again,
     * its days may have been taken in the meantime.
     */
    public function reopen(WorkdayShift $shift): WorkdayShift
    {
        if ($shift->getStatus() === AbsenceStatus::APPROVED) {
            return $this->request($shift, true);
        }
        if ($shift->getStatus() !== AbsenceStatus::REJECTED) {
            throw $this->error('not_requested');
        }

        $this->validate($shift);
        $this->assertNotLocked($shift);
        $this->backToRequested($shift);
        $this->notifier->notifyShiftRequested($shift);

        return $shift;
    }

    public function delete(WorkdayShift $shift, User $actor): void
    {
        $this->assertNoAbsenceOnTarget($shift);
        $this->assertNotLocked($shift);

        $wasApproved = $shift->getStatus() === AbsenceStatus::APPROVED;
        $this->repository->remove($shift);
        $this->overrides->reset($shift->getUser());
        if ($wasApproved) {
            $this->notifier->notifyShiftWithdrawn($shift, $actor);
        }
    }

    private function backToRequested(WorkdayShift $shift): void
    {
        $shift->setStatus(AbsenceStatus::REQUESTED);
        $shift->setApprovedBy(null);
        $shift->setApprovedAt(null);
        $this->repository->save($shift);
        $this->overrides->reset($shift->getUser());
    }

    private function markApproved(WorkdayShift $shift, User $approver): void
    {
        $shift->setStatus(AbsenceStatus::APPROVED);
        $shift->setApprovedBy($approver);
        $shift->setApprovedAt(new \DateTimeImmutable());
    }

    private function error(string $key): \InvalidArgumentException
    {
        return new \InvalidArgumentException('holiday.error.' . $key);
    }

    private function isOutsideContract(User $user, string $ymd): bool
    {
        $start = $user->getWorkStartingDay()?->format('Y-m-d');
        $end = $user->getLastWorkingDay()?->format('Y-m-d');

        return ($start !== null && $ymd < $start) || ($end !== null && $ymd > $end);
    }

    private function hasApprovedAbsence(User $user, \DateTimeImmutable $day): bool
    {
        return $this->absenceRepository->findApprovedBetween($user, $day, $day) !== [];
    }

    /** An approved shift cannot go while an approved absence sits on its target day. */
    private function assertNoAbsenceOnTarget(WorkdayShift $shift): void
    {
        $user = $shift->getUser();
        $to = $shift->getToDate();
        if ($shift->getStatus() !== AbsenceStatus::APPROVED || $user === null || $to === null) {
            return;
        }

        if ($this->hasApprovedAbsence($user, $to)) {
            throw $this->error('shift_has_absence');
        }
    }

    /** Both days are checked on their own: the week may span two months. */
    private function assertNotLocked(WorkdayShift $shift): void
    {
        $user = $shift->getUser();
        if ($user === null) {
            return;
        }

        foreach ([$shift->getFromDate(), $shift->getToDate()] as $day) {
            if ($day === null) {
                continue;
            }
            try {
                $this->periodLockGuard->assertOpen($user, $day, $day);
            } catch (\RuntimeException $e) {
                if ($e->getMessage() !== 'holiday.error.month_locked') {
                    throw $e;
                }
                throw $this->error('shift_locked');
            }
        }
    }

    private function assertNotOwn(WorkdayShift $shift, User $approver): void
    {
        $user = $shift->getUser();
        if ($user !== null && $this->approverResolver->isSame($user, $approver)) {
            throw $this->error('self_approval');
        }
    }

    private function assertRequested(WorkdayShift $shift): void
    {
        if ($shift->getStatus() !== AbsenceStatus::REQUESTED) {
            throw $this->error('not_requested');
        }
    }
}
