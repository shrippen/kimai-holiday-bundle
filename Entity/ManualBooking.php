<?php

namespace KimaiPlugin\HolidayBundle\Entity;

use App\Entity\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use KimaiPlugin\HolidayBundle\Enum\ManualBookingKind;
use KimaiPlugin\HolidayBundle\Repository\ManualBookingRepository;

#[ORM\Entity(repositoryClass: ManualBookingRepository::class)]
#[ORM\Table(name: 'kimai2_ext_holiday_booking')]
class ManualBooking
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(type: Types::STRING, length: 16, enumType: ManualBookingKind::class)]
    private ManualBookingKind $kind = ManualBookingKind::TIME;

    /** Signed seconds for TIME, or signed days (stored as float * 100 as int cents-of-day) — use amountSeconds for time, amountDays for holiday. */
    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $amountSeconds = 0;

    #[ORM\Column(type: Types::FLOAT, options: ['default' => 0])]
    private float $amountDays = 0.0;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $bookingDate = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $comment = '';

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

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

    public function getKind(): ManualBookingKind
    {
        return $this->kind;
    }

    public function setKind(ManualBookingKind $kind): self
    {
        $this->kind = $kind;

        return $this;
    }

    public function getAmountSeconds(): int
    {
        return $this->amountSeconds;
    }

    public function setAmountSeconds(int $amountSeconds): self
    {
        $this->amountSeconds = $amountSeconds;

        return $this;
    }

    public function getAmountDays(): float
    {
        return $this->amountDays;
    }

    public function setAmountDays(float $amountDays): self
    {
        $this->amountDays = $amountDays;

        return $this;
    }

    public function getBookingDate(): ?\DateTimeImmutable
    {
        return $this->bookingDate;
    }

    public function setBookingDate(\DateTimeImmutable $bookingDate): self
    {
        $this->bookingDate = $bookingDate;

        return $this;
    }

    public function getComment(): string
    {
        return $this->comment;
    }

    public function setComment(string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): self
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
