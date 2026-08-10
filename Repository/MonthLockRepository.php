<?php

namespace KimaiPlugin\HolidayBundle\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use KimaiPlugin\HolidayBundle\Entity\MonthLock;

/**
 * @extends ServiceEntityRepository<MonthLock>
 */
class MonthLockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MonthLock::class);
    }

    public function findOne(User $user, int $year, int $month): ?MonthLock
    {
        return $this->findOneBy(['user' => $user, 'year' => $year, 'month' => $month]);
    }

    /**
     * @return MonthLock[]
     */
    public function findByUserAndYear(User $user, int $year): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.user = :user')
            ->andWhere('l.year = :year')
            ->setParameter('user', $user)
            ->setParameter('year', $year)
            ->orderBy('l.month', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function isLocked(User $user, int $year, int $month): bool
    {
        return $this->findOne($user, $year, $month) !== null;
    }

    public function isDateLocked(User $user, \DateTimeInterface $date): bool
    {
        return $this->isLocked($user, (int) $date->format('Y'), (int) $date->format('n'));
    }

    public function save(MonthLock $lock, bool $flush = true): void
    {
        $this->getEntityManager()->persist($lock);
        if ($flush) {
            $this->flush();
        }
    }

    public function remove(MonthLock $lock, bool $flush = true): void
    {
        $this->getEntityManager()->remove($lock);
        if ($flush) {
            $this->flush();
        }
    }

    public function flush(): void
    {
        $this->getEntityManager()->flush();
    }
}
