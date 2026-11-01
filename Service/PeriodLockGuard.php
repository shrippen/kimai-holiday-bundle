<?php

namespace KimaiPlugin\HolidayBundle\Service;

use App\Entity\User;
use App\Repository\WorkingTimeRepository;
use KimaiPlugin\HolidayBundle\Repository\MonthLockRepository;

/**
 * Refuses changes in periods that are closed: Kimai's working-time approval or a plugin month lock.
 */
class PeriodLockGuard
{
    public function __construct(
        private readonly WorkingTimeRepository $workingTimeRepository,
        private readonly MonthLockRepository $monthLockRepository,
    ) {
    }

    /**
     * @throws \RuntimeException holiday.error.month_locked
     */
    public function assertOpen(User $user, \DateTimeInterface $from, \DateTimeInterface $to): void
    {
        $cursor = $from instanceof \DateTimeImmutable
            ? $from
            : \DateTimeImmutable::createFromInterface($from);
        $endDay = $to instanceof \DateTimeImmutable
            ? $to
            : \DateTimeImmutable::createFromInterface($to);

        // Kimai's own working-time approval (Arbeitszeiten → month approved) locks everything up to that date.
        // Queried directly: WorkingTimeService::isApproved() may flush the entity manager (caches a user preference).
        $approvedUntil = $this->workingTimeRepository->getLatestApprovalDate($user);
        if ($approvedUntil !== null && $cursor->format('Y-m-d') <= $approvedUntil->format('Y-m-d')) {
            throw new \RuntimeException('holiday.error.month_locked');
        }

        // Plugin locks are per month: check each touched month once.
        $cursor = $cursor->modify('first day of this month')->setTime(0, 0);
        while ($cursor <= $endDay) {
            if ($this->monthLockRepository->isDateLocked($user, $cursor)) {
                throw new \RuntimeException('holiday.error.month_locked');
            }
            $cursor = $cursor->modify('+1 month');
        }
    }
}
