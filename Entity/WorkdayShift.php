<?php

namespace KimaiPlugin\HolidayBundle\Entity;

use App\Entity\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use KimaiPlugin\HolidayBundle\Enum\AbsenceStatus;
use KimaiPlugin\HolidayBundle\Repository\WorkdayShiftRepository;

/** A day shift: one contract day moves to another day of the same week. */
#[ORM\Entity(repositoryClass: WorkdayShiftRepository::class)]
#[ORM\Table(name: 'kimai2_ext_holiday_workday_shift')]
#[ORM\Index(columns: ['user_id', 'from_date'], name: 'idx_holiday_shift_user_from')]
#[ORM\Index(columns: ['user_id', 'to_date'], name: 'idx_holiday_shift_user_to')]
class WorkdayShift
{
    public const SIDE_FROM = 'from';
    public const SIDE_TO = 'to';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(name: 'from_date', type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $fromDate = null;

    #[ORM\Column(name: 'to_date', type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $toDate = null;

    #[ORM\Column(type: Types::STRING, length: 32, enumType: AbsenceStatus::class)]
    private AbsenceStatus $status = AbsenceStatus::REQUESTED;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'approved_by_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $approvedBy = null;

    #[ORM\Column(name: 'approved_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
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

    public function getFromDate(): ?\DateTimeImmutable
    {
        return $this->fromDate;
    }

    public function setFromDate(\DateTimeImmutable $fromDate): self
    {
        $this->fromDate = $fromDate;

        return $this;
    }

    public function getToDate(): ?\DateTimeImmutable
    {
        return $this->toDate;
    }

    public function setToDate(\DateTimeImmutable $toDate): self
    {
        $this->toDate = $toDate;

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

    /** Which side of the shift the day is: SIDE_FROM, SIDE_TO or null. */
    public function covers(\DateTimeInterface $day): ?string
    {
        $ymd = $day->format('Y-m-d');
        if ($this->fromDate?->format('Y-m-d') === $ymd) {
            return self::SIDE_FROM;
        }
        if ($this->toDate?->format('Y-m-d') === $ymd) {
            return self::SIDE_TO;
        }

        return null;
    }

    public function isActive(): bool
    {
        return $this->status !== AbsenceStatus::REJECTED;
    }
}
