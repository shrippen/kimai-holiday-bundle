<?php

namespace KimaiPlugin\HolidayBundle\Service;

use App\Entity\User;
use KimaiPlugin\HolidayBundle\Enum\AbsenceStatus;
use KimaiPlugin\HolidayBundle\Repository\WorkdayShiftRepository;

/**
 * Approved workday shifts as a per-day lookup: the origin day F loses its
 * expected hours, the target day T takes over F's. One query per user and year.
 */
class WorkdayShiftOverrides
{
    /** @var array<string, array{from: array<string, \DateTimeImmutable>, to: array<string, \DateTimeImmutable>}> "userKey-year" => index */
    private array $cache = [];

    public function __construct(
        private readonly WorkdayShiftRepository $repository,
    ) {
    }

    /**
     * For a target day: the origin day whose hours move here.
     */
    public function sourceFor(User $user, \DateTimeInterface $day): ?\DateTimeImmutable
    {
        return $this->index($user, (int) $day->format('Y'))['to'][$day->format('Y-m-d')] ?? null;
    }

    /**
     * Whether the day is an origin day whose hours moved elsewhere.
     */
    public function isShiftedAway(User $user, \DateTimeInterface $day): bool
    {
        return isset($this->index($user, (int) $day->format('Y'))['from'][$day->format('Y-m-d')]);
    }

    /**
     * For an origin day: the target day its hours moved to.
     */
    public function targetFor(User $user, \DateTimeInterface $day): ?\DateTimeImmutable
    {
        return $this->index($user, (int) $day->format('Y'))['from'][$day->format('Y-m-d')] ?? null;
    }

    public function reset(?User $user = null): void
    {
        if ($user === null) {
            $this->cache = [];

            return;
        }

        $prefix = $this->userKey($user) . '-';
        foreach (array_keys($this->cache) as $key) {
            if (str_starts_with($key, $prefix)) {
                unset($this->cache[$key]);
            }
        }
    }

    /**
     * @return array{from: array<string, \DateTimeImmutable>, to: array<string, \DateTimeImmutable>}
     */
    private function index(User $user, int $year): array
    {
        $key = $this->userKey($user) . '-' . $year;
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $index = ['from' => [], 'to' => []];
        $shifts = $this->repository->findApprovedForUserBetween(
            $user,
            new \DateTimeImmutable($year . '-01-01'),
            new \DateTimeImmutable($year . '-12-31')
        );
        foreach ($shifts as $shift) {
            $from = $shift->getFromDate();
            $to = $shift->getToDate();
            if ($shift->getStatus() !== AbsenceStatus::APPROVED || $from === null || $to === null) {
                continue;
            }
            $index['from'][$from->format('Y-m-d')] = \DateTimeImmutable::createFromInterface($to)->setTime(0, 0);
            $index['to'][$to->format('Y-m-d')] = \DateTimeImmutable::createFromInterface($from)->setTime(0, 0);
        }

        return $this->cache[$key] = $index;
    }

    private function userKey(User $user): string
    {
        return $user->getId() !== null ? 'id:' . $user->getId() : 'obj:' . spl_object_id($user);
    }
}
