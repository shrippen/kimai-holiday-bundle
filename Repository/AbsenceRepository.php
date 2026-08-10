<?php

namespace KimaiPlugin\HolidayBundle\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use KimaiPlugin\HolidayBundle\Entity\Absence;
use KimaiPlugin\HolidayBundle\Enum\AbsenceStatus;
use KimaiPlugin\HolidayBundle\Enum\AbsenceType;

/**
 * @extends ServiceEntityRepository<Absence>
 */
class AbsenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Absence::class);
    }

    /**
     * @return Absence[]
     */
    public function findByUserAndYear(User $user, int $year): array
    {
        $start = new \DateTimeImmutable(sprintf('%d-01-01', $year));
        $end = new \DateTimeImmutable(sprintf('%d-12-31', $year));

        return $this->createQueryBuilder('a')
            ->andWhere('a.user = :user')
            ->andWhere('a.startDate <= :end')
            ->andWhere('a.endDate >= :start')
            ->setParameter('user', $user)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('a.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Absence[]
     */
    public function findApprovedBetween(User $user, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.user = :user')
            ->andWhere('a.status = :status')
            ->andWhere('a.startDate <= :to')
            ->andWhere('a.endDate >= :from')
            ->setParameter('user', $user)
            ->setParameter('status', AbsenceStatus::APPROVED)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('a.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param User[] $users
     * @return Absence[]
     */
    public function findApprovedForUsersBetween(array $users, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        if ($users === []) {
            return [];
        }

        return $this->createQueryBuilder('a')
            ->andWhere('a.user IN (:users)')
            ->andWhere('a.status = :status')
            ->andWhere('a.startDate <= :to')
            ->andWhere('a.endDate >= :from')
            ->setParameter('users', $users)
            ->setParameter('status', AbsenceStatus::APPROVED)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('a.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Absence[]
     */
    public function findApprovedVacationsInYear(User $user, int $year): array
    {
        $start = new \DateTimeImmutable(sprintf('%d-01-01', $year));
        $end = new \DateTimeImmutable(sprintf('%d-12-31', $year));

        return $this->createQueryBuilder('a')
            ->andWhere('a.user = :user')
            ->andWhere('a.type = :type')
            ->andWhere('a.status = :status')
            ->andWhere('a.startDate <= :end')
            ->andWhere('a.endDate >= :start')
            ->setParameter('user', $user)
            ->setParameter('type', AbsenceType::VACATION)
            ->setParameter('status', AbsenceStatus::APPROVED)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getResult();
    }

    public function save(Absence $absence, bool $flush = true): void
    {
        $this->getEntityManager()->persist($absence);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Absence $absence, bool $flush = true): void
    {
        $this->getEntityManager()->remove($absence);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
