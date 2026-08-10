<?php

namespace KimaiPlugin\HolidayBundle\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Per-user secret token for public ICS calendar subscription URLs.
 */
class UserIcsTokenService
{
    public const PREFERENCE_NAME = 'holiday_ics_token';

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function getOrCreateToken(User $user): string
    {
        $token = $user->getPreferenceValue(self::PREFERENCE_NAME);
        if (\is_string($token) && $token !== '') {
            return $token;
        }

        return $this->regenerateToken($user);
    }

    public function regenerateToken(User $user): string
    {
        $token = bin2hex(random_bytes(24));
        $user->setPreferenceValue(self::PREFERENCE_NAME, $token);
        $this->userRepository->saveUser($user);

        return $token;
    }

    public function findUserByToken(string $token): ?User
    {
        if ($token === '' || !preg_match('/^[a-f0-9]{48}$/', $token)) {
            return null;
        }

        return $this->entityManager->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->innerJoin('u.preferences', 'p')
            ->andWhere('p.name = :name')
            ->andWhere('p.value = :token')
            ->setParameter('name', self::PREFERENCE_NAME)
            ->setParameter('token', $token)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
