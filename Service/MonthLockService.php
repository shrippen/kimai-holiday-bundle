<?php

namespace KimaiPlugin\HolidayBundle\Service;

use App\Entity\User;
use KimaiPlugin\HolidayBundle\Entity\MonthLock;
use KimaiPlugin\HolidayBundle\Repository\MonthLockRepository;

class MonthLockService
{
    public function __construct(private readonly MonthLockRepository $monthLockRepository)
    {
    }

    /**
     * Locking a month also locks all earlier months in the same year.
     */
    public function lock(User $user, int $year, int $month, User $actor): void
    {
        for ($m = 1; $m <= $month; ++$m) {
            if ($this->monthLockRepository->isLocked($user, $year, $m)) {
                continue;
            }

            $lock = new MonthLock();
            $lock->setUser($user);
            $lock->setYear($year);
            $lock->setMonth($m);
            $lock->setLockedBy($actor);
            $lock->setLockedAt(new \DateTimeImmutable());
            $this->monthLockRepository->save($lock, false);
        }

        $this->monthLockRepository->flush();
    }

    /**
     * Unlocking a month also unlocks all later months in the same year.
     */
    public function unlock(User $user, int $year, int $month): void
    {
        $locks = $this->monthLockRepository->findByUserAndYear($user, $year);
        foreach ($locks as $lock) {
            if ($lock->getMonth() >= $month) {
                $this->monthLockRepository->remove($lock, false);
            }
        }

        $this->monthLockRepository->flush();
    }

    /**
     * @return array<int, bool> month => locked
     */
    public function getLocksMap(User $user, int $year): array
    {
        $map = array_fill(1, 12, false);
        foreach ($this->monthLockRepository->findByUserAndYear($user, $year) as $lock) {
            $map[$lock->getMonth()] = true;
        }

        return $map;
    }
}
