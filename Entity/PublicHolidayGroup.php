<?php

namespace KimaiPlugin\HolidayBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use KimaiPlugin\HolidayBundle\Repository\PublicHolidayGroupRepository;

#[ORM\Entity(repositoryClass: PublicHolidayGroupRepository::class)]
#[ORM\Table(name: 'kimai2_ext_holiday_ph_group')]
class PublicHolidayGroup
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $name = '';

    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    private ?string $country = null;

    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    private ?string $region = null;

    /** Stored ICS subscription URL for automatic re-sync. */
    #[ORM\Column(type: Types::STRING, length: 512, nullable: true)]
    private ?string $icsUrl = null;

    /** Import/sync holidays on or after 1 January of this year. */
    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $icsFromYear = null;

    /** @var Collection<int, PublicHoliday> */
    #[ORM\OneToMany(mappedBy: 'holidayGroup', targetEntity: PublicHoliday::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $holidays;

    public function __construct()
    {
        $this->holidays = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): self
    {
        $this->country = $country;

        return $this;
    }

    public function getRegion(): ?string
    {
        return $this->region;
    }

    public function setRegion(?string $region): self
    {
        $this->region = $region;

        return $this;
    }

    public function getIcsUrl(): ?string
    {
        return $this->icsUrl;
    }

    public function setIcsUrl(?string $icsUrl): self
    {
        $this->icsUrl = $icsUrl;

        return $this;
    }

    public function getIcsFromYear(): ?int
    {
        return $this->icsFromYear;
    }

    public function setIcsFromYear(?int $icsFromYear): self
    {
        $this->icsFromYear = $icsFromYear;

        return $this;
    }

    /**
     * @return Collection<int, PublicHoliday>
     */
    public function getHolidays(): Collection
    {
        return $this->holidays;
    }

    public function addHoliday(PublicHoliday $holiday): self
    {
        if (!$this->holidays->contains($holiday)) {
            $this->holidays->add($holiday);
            $holiday->setHolidayGroup($this);
        }

        return $this;
    }

    public function removeHoliday(PublicHoliday $holiday): self
    {
        if ($this->holidays->removeElement($holiday) && $holiday->getHolidayGroup() === $this) {
            $holiday->setHolidayGroup(null);
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
