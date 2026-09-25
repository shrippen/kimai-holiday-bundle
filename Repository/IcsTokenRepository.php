<?php

namespace KimaiPlugin\HolidayBundle\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use KimaiPlugin\HolidayBundle\Entity\IcsToken;

/**
 * @extends ServiceEntityRepository<IcsToken>
 */
class IcsTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IcsToken::class);
    }

    public function findByUser(User $user): ?IcsToken
    {
        if ($user->getId() === null) {
            return null;
        }

        return $this->find($user->getId());
    }

    /**
     * Uses the unique index on the token column.
     */
    public function findByToken(string $token): ?IcsToken
    {
        return $this->findOneBy(['token' => $token]);
    }

    public function save(IcsToken $token): void
    {
        $this->getEntityManager()->persist($token);
        $this->getEntityManager()->flush();
    }
}
