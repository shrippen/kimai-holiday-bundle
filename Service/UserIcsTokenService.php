<?php

namespace KimaiPlugin\HolidayBundle\Service;

use App\Entity\User;
use KimaiPlugin\HolidayBundle\Entity\IcsToken;
use KimaiPlugin\HolidayBundle\Repository\IcsTokenRepository;

/**
 * Per-user secret token for public ICS calendar subscription URLs.
 *
 * Stored in its own table ({@see IcsToken}), not as a user preference: Kimai returns all preferences in
 * `/api/users/me` and `/api/users/{id}` and hands them to invoice and export templates. Migration Version20260925120000 moved the former
 * preference `holiday_ics_token` into the table.
 */
class UserIcsTokenService
{
    public function __construct(private readonly IcsTokenRepository $repository)
    {
    }

    public function getToken(User $user): ?string
    {
        return $this->repository->findByUser($user)?->getToken();
    }

    public function getOrCreateToken(User $user): string
    {
        return $this->getToken($user) ?? $this->regenerateToken($user);
    }

    public function regenerateToken(User $user): string
    {
        $token = bin2hex(random_bytes(24));
        $entity = $this->repository->findByUser($user);
        if ($entity === null) {
            $entity = new IcsToken($user, $token);
        } else {
            $entity->setToken($token);
        }
        $this->repository->save($entity);

        return $token;
    }

    public function findUserByToken(string $token): ?User
    {
        if ($token === '' || !preg_match('/^[a-f0-9]{48}$/', $token)) {
            return null;
        }

        $user = $this->repository->findByToken($token)?->getUser();

        // Disabled accounts must not leak their calendar anymore.
        return $user instanceof User && $user->isEnabled() ? $user : null;
    }
}
