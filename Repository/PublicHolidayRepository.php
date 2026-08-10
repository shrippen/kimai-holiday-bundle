<?php

namespace KimaiPlugin\HolidayBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use KimaiPlugin\HolidayBundle\Entity\PublicHoliday;
use KimaiPlugin\HolidayBundle\Entity\PublicHolidayGroup;

/**
 * @extends ServiceEntityRepository<PublicHoliday>
 */
class PublicHolidayRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PublicHoliday::class);
    }

    /**
     * @return PublicHoliday[]
     */
    public function findByGroupAndYear(PublicHolidayGroup $group, int $year): array
    {
        $start = new \DateTimeImmutable(sprintf('%d-01-01', $year));
        $end = new \DateTimeImmutable(sprintf('%d-12-31', $year));

        return $this->createQueryBuilder('h')
            ->andWhere('h.holidayGroup = :group')
            ->andWhere('h.date BETWEEN :start AND :end')
            ->setParameter('group', $group)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('h.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return PublicHoliday[]
     */
    public function findByGroupBetween(PublicHolidayGroup $group, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.holidayGroup = :group')
            ->andWhere('h.date BETWEEN :from AND :to')
            ->setParameter('group', $group)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('h.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByGroupAndDate(PublicHolidayGroup $group, \DateTimeInterface $date): ?PublicHoliday
    {
        $day = $date instanceof \DateTimeImmutable
            ? $date->setTime(0, 0)
            : \DateTimeImmutable::createFromInterface($date)->setTime(0, 0);

        return $this->findOneBy(['holidayGroup' => $group, 'date' => $day]);
    }

    public function save(PublicHoliday $holiday, bool $flush = true): void
    {
        $this->getEntityManager()->persist($holiday);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(PublicHoliday $holiday, bool $flush = true): void
    {
        $this->getEntityManager()->remove($holiday);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
