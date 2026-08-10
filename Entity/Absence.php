<?php

namespace KimaiPlugin\HolidayBundle\Entity;

use App\Entity\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use KimaiPlugin\HolidayBundle\Enum\AbsenceStatus;
use KimaiPlugin\HolidayBundle\Enum\AbsenceType;
use KimaiPlugin\HolidayBundle\Repository\AbsenceRepository;

#[ORM\Entity(repositoryClass: AbsenceRepository::class)]
#[ORM\Table(name: 'kimai2_ext_holiday_absence')]
#[ORM\Index(columns: ['start_date', 'end_date'], name: 'idx_holiday_absence_dates')]
class Absence
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(type: Types::STRING, length: 32, enumType: AbsenceType::class)]
    private AbsenceType $type = AbsenceType::VACATION;

    #[ORM\Column(type: Types::STRING, length: 32, enumType: AbsenceStatus::class)]
    private AbsenceStatus $status = AbsenceStatus::NEW;

    #[ORM\Column(name: 'start_date', type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $startDate = null;

    #[ORM\Column(name: 'end_date', type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $endDate = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $halfDay = false;

    /** Duration in seconds for OTHER absences (optional). UI edits this in hours. */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $duration = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $approvedBy = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $approvedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
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

    public function getType(): AbsenceType
    {
        return $this->type;
    }

    public function setType(AbsenceType $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getStatus(): AbsenceStatus
    {
        return $this->status;
    }

    public function setStatus(AbsenceStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getStartDate(): ?\DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeImmutable $startDate): self
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): ?\DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setEndDate(\DateTimeImmutable $endDate): self
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function isHalfDay(): bool
    {
        return $this->halfDay;
    }

    public function setHalfDay(bool $halfDay): self
    {
        $this->halfDay = $halfDay;

        return $this;
    }

    public function getDuration(): ?int
    {
        return $this->duration;
    }

    public function setDuration(?int $duration): self
    {
        $this->duration = $duration;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getApprovedBy(): ?User
    {
        return $this->approvedBy;
    }

    public function setApprovedBy(?User $approvedBy): self
    {
        $this->approvedBy = $approvedBy;

        return $this;
    }

    public function getApprovedAt(): ?\DateTimeImmutable
    {
        return $this->approvedAt;
    }

    public function setApprovedAt(?\DateTimeImmutable $approvedAt): self
    {
        $this->approvedAt = $approvedAt;

        return $this;
    }

    public function coversDate(\DateTimeInterface $date): bool
    {
        if ($this->startDate === null || $this->endDate === null) {
            return false;
        }

        $day = $date instanceof \DateTimeImmutable
            ? $date->setTime(0, 0)
            : \DateTimeImmutable::createFromInterface($date)->setTime(0, 0);

        return $day >= $this->startDate && $day <= $this->endDate;
    }
}
