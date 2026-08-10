<?php

namespace KimaiPlugin\HolidayBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use KimaiPlugin\HolidayBundle\Entity\PublicHolidayGroup;

/**
 * @extends ServiceEntityRepository<PublicHolidayGroup>
 */
class PublicHolidayGroupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PublicHolidayGroup::class);
    }

    /**
     * @return PublicHolidayGroup[]
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('g')
            ->orderBy('g.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function save(PublicHolidayGroup $group, bool $flush = true): void
    {
        $this->getEntityManager()->persist($group);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(PublicHolidayGroup $group, bool $flush = true): void
    {
        $this->getEntityManager()->remove($group);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
