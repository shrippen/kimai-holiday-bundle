<?php

namespace KimaiPlugin\HolidayBundle\Entity;

use App\Entity\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use KimaiPlugin\HolidayBundle\Repository\MonthLockRepository;

#[ORM\Entity(repositoryClass: MonthLockRepository::class)]
#[ORM\Table(name: 'kimai2_ext_holiday_month_lock')]
#[ORM\UniqueConstraint(name: 'uniq_holiday_month_lock', columns: ['user_id', 'year', 'month'])]
class MonthLock
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $year = 0;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $month = 0;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $lockedBy = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $lockedAt;

    public function __construct()
    {
        $this->lockedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getYear(): int
    {
        return $this->year;
    }

    public function setYear(int $year): self
    {
        $this->year = $year;

        return $this;
    }

    public function getMonth(): int
    {
        return $this->month;
    }

    public function setMonth(int $month): self
    {
        $this->month = $month;

        return $this;
    }

    public function getLockedBy(): ?User
    {
        return $this->lockedBy;
    }

    public function setLockedBy(?User $lockedBy): self
    {
        $this->lockedBy = $lockedBy;

        return $this;
    }

    public function getLockedAt(): \DateTimeImmutable
    {
        return $this->lockedAt;
    }

    public function setLockedAt(\DateTimeImmutable $lockedAt): self
    {
        $this->lockedAt = $lockedAt;

        return $this;
    }
}
