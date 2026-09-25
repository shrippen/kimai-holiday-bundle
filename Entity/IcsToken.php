<?php

namespace KimaiPlugin\HolidayBundle\Entity;

use App\Entity\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use KimaiPlugin\HolidayBundle\Repository\IcsTokenRepository;

/**
 * Secret token of a user's personal ICS feed (`/holiday/ics/{token}.ics`).
 *
 * Kept out of Kimai's user preferences on purpose: those are serialized by the user API
 * (`/api/users/me`, `/api/users/{id}`) and passed to invoice and export templates (`user.meta.*`).
 */
#[ORM\Entity(repositoryClass: IcsTokenRepository::class)]
#[ORM\Table(name: 'kimai2_ext_holiday_ics_token')]
#[ORM\UniqueConstraint(name: 'uniq_holiday_ics_token', columns: ['token'])]
class IcsToken
{
    #[ORM\Id]
    #[ORM\OneToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(name: 'token', type: Types::STRING, length: 48, nullable: false)]
    private string $token;

    public function __construct(User $user, string $token)
    {
        $this->user = $user;
        $this->token = $token;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function setToken(string $token): self
    {
        $this->token = $token;

        return $this;
    }
}
