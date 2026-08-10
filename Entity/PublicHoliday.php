<?php

namespace KimaiPlugin\HolidayBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use KimaiPlugin\HolidayBundle\Repository\PublicHolidayRepository;

#[ORM\Entity(repositoryClass: PublicHolidayRepository::class)]
#[ORM\Table(name: 'kimai2_ext_holiday_public')]
#[ORM\Index(columns: ['holiday_date'], name: 'idx_holiday_public_date')]
class PublicHoliday
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: PublicHolidayGroup::class, inversedBy: 'holidays')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?PublicHolidayGroup $holidayGroup = null;

    #[ORM\Column(name: 'holiday_date', type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $date = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $name = '';

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $halfDay = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getHolidayGroup(): ?PublicHolidayGroup
    {
        return $this->holidayGroup;
    }

    public function setHolidayGroup(?PublicHolidayGroup $holidayGroup): self
    {
        $this->holidayGroup = $holidayGroup;

        return $this;
    }

    public function getDate(): ?\DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(\DateTimeImmutable $date): self
    {
        $this->date = $date;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

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
}
