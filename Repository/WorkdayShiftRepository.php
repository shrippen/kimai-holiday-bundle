<?php

namespace KimaiPlugin\HolidayBundle\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use KimaiPlugin\HolidayBundle\Entity\WorkdayShift;
use KimaiPlugin\HolidayBundle\Enum\AbsenceStatus;

/**
 * @extends ServiceEntityRepository<WorkdayShift>
 */
class WorkdayShiftRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WorkdayShift::class);
    }

    /**
     * @return WorkdayShift[]
     */
    public function findApprovedForUserBetween(User $user, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        return $this->findApprovedForUsersBetween([$user], $from, $to);
    }

    /**
     * @param User[] $users
     * @return WorkdayShift[]
     */
    public function findApprovedForUsersBetween(array $users, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        return $this->betweenForUsers($users, [AbsenceStatus::APPROVED], $from, $to);
    }

    /**
     * Approved and requested shifts (the plan shows both).
     *
     * @param User[] $users
     * @return WorkdayShift[]
     */
    public function findVisibleForUsersBetween(array $users, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        return $this->betweenForUsers($users, [AbsenceStatus::APPROVED, AbsenceStatus::REQUESTED], $from, $to);
    }

    /**
     * Open requests of the given users, earliest source day first.
     *
     * @param User[] $users
     * @return WorkdayShift[]
     */
    public function findRequestedForUsers(array $users): array
    {
        if ($users === []) {
            return [];
        }

        return $this->createQueryBuilder('s')
            ->andWhere('s.user IN (:users)')
            ->andWhere('s.status = :status')
            ->setParameter('users', $users)
            ->setParameter('status', AbsenceStatus::REQUESTED->value)
            ->orderBy('s.fromDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Shifts of the user (not rejected) that have the day as source or target.
     *
     * @return WorkdayShift[]
     */
    public function findActiveTouching(User $user, \DateTimeInterface $day, ?int $excludeId = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->andWhere('s.user = :user')
            ->andWhere('s.status != :rejected')
            ->andWhere('s.fromDate = :day OR s.toDate = :day')
            ->setParameter('user', $user)
            ->setParameter('rejected', AbsenceStatus::REJECTED->value)
            ->setParameter('day', $day, 'date_immutable');

        if ($excludeId !== null) {
            $qb->andWhere('s.id != :exclude')->setParameter('exclude', $excludeId);
        }

        return $qb->getQuery()->getResult();
    }

    public function save(WorkdayShift $shift, bool $flush = true): void
    {
        $this->getEntityManager()->persist($shift);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(WorkdayShift $shift, bool $flush = true): void
    {
        $this->getEntityManager()->remove($shift);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @param User[] $users
     * @param AbsenceStatus[] $statuses
     * @return WorkdayShift[]
     */
    private function betweenForUsers(array $users, array $statuses, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        if ($users === []) {
            return [];
        }

        /** @var QueryBuilder $qb */
        $qb = $this->createQueryBuilder('s')
            ->andWhere('s.user IN (:users)')
            ->andWhere('s.status IN (:status)')
            ->andWhere('(s.fromDate >= :from AND s.fromDate <= :to) OR (s.toDate >= :from AND s.toDate <= :to)')
            ->setParameter('users', $users)
            ->setParameter('status', array_map(static fn (AbsenceStatus $s): string => $s->value, $statuses))
            ->setParameter('from', $from, 'date_immutable')
            ->setParameter('to', $to, 'date_immutable')
            ->orderBy('s.fromDate', 'ASC');

        return $qb->getQuery()->getResult();
    }
}
