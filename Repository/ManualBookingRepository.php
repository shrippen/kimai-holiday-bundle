<?php

namespace KimaiPlugin\HolidayBundle\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use KimaiPlugin\HolidayBundle\Entity\ManualBooking;
use KimaiPlugin\HolidayBundle\Enum\ManualBookingKind;

/**
 * @extends ServiceEntityRepository<ManualBooking>
 */
class ManualBookingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ManualBooking::class);
    }

    /**
     * @return ManualBooking[]
     */
    public function findByUserAndYear(User $user, int $year): array
    {
        $start = new \DateTimeImmutable(sprintf('%d-01-01', $year));
        $end = new \DateTimeImmutable(sprintf('%d-12-31', $year));

        return $this->createQueryBuilder('b')
            ->andWhere('b.user = :user')
            ->andWhere('b.bookingDate BETWEEN :start AND :end')
            ->setParameter('user', $user)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('b.bookingDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function sumTimeSecondsUntil(User $user, \DateTimeInterface $until): int
    {
        $result = $this->createQueryBuilder('b')
            ->select('COALESCE(SUM(b.amountSeconds), 0)')
            ->andWhere('b.user = :user')
            ->andWhere('b.kind = :kind')
            ->andWhere('b.bookingDate <= :until')
            ->setParameter('user', $user)
            ->setParameter('kind', ManualBookingKind::TIME)
            ->setParameter('until', $until)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result;
    }

    public function sumHolidayDaysInYear(User $user, int $year): float
    {
        $start = new \DateTimeImmutable(sprintf('%d-01-01', $year));
        $end = new \DateTimeImmutable(sprintf('%d-12-31', $year));

        $result = $this->createQueryBuilder('b')
            ->select('COALESCE(SUM(b.amountDays), 0)')
            ->andWhere('b.user = :user')
            ->andWhere('b.kind = :kind')
            ->andWhere('b.bookingDate BETWEEN :start AND :end')
            ->setParameter('user', $user)
            ->setParameter('kind', ManualBookingKind::HOLIDAY)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) $result;
    }

    public function save(ManualBooking $booking, bool $flush = true): void
    {
        $this->getEntityManager()->persist($booking);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
