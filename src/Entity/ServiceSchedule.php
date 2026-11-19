<?php

namespace App\Entity;

use App\Repository\ServiceScheduleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: ServiceScheduleRepository::class)]
#[ORM\Table(name: 'service_schedule')]
#[ORM\UniqueConstraint(name: 'uniq_service_day', columns: ['service_id', 'day_of_week'])]
class ServiceSchedule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Service::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Service $service = null;

    /**
     * 1 = Lundi, 2 = Mardi, ..., 7 = Dimanche (ISO-8601)
     */
    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\Range(min: 1, max: 7, notInRangeMessage: 'Le jour doit être compris entre 1 (Lundi) et 7 (Dimanche).')]
    private int $dayOfWeek = 1;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    private ?\DateTimeInterface $openingTime = null;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    private ?\DateTimeInterface $closingTime = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\Positive(message: 'La durée des créneaux doit être supérieure à 0.')]
    private int $slotDurationMinutes = 20;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isActive = true;

    public function __construct()
    {
        $this->openingTime = new \DateTime('08:30:00');
        $this->closingTime = new \DateTime('16:30:00');
        $this->slotDurationMinutes = 20;
        $this->isActive = true;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getService(): ?Service
    {
        return $this->service;
    }

    public function setService(?Service $service): static
    {
        $this->service = $service;
        return $this;
    }

    public function getDayOfWeek(): int
    {
        return $this->dayOfWeek;
    }

    public function setDayOfWeek(int $dayOfWeek): static
    {
        $this->dayOfWeek = $dayOfWeek;
        return $this;
    }

    public function getDayName(): string
    {
        return match ($this->dayOfWeek) {
            1 => 'Lundi',
            2 => 'Mardi',
            3 => 'Mercredi',
            4 => 'Jeudi',
            5 => 'Vendredi',
            6 => 'Samedi',
            7 => 'Dimanche',
            default => 'Jour ' . $this->dayOfWeek,
        };
    }

    public function getOpeningTime(): ?\DateTimeInterface
    {
        return $this->openingTime;
    }

    public function setOpeningTime(?\DateTimeInterface $openingTime): static
    {
        $this->openingTime = $openingTime;
        return $this;
    }

    public function getClosingTime(): ?\DateTimeInterface
    {
        return $this->closingTime;
    }

    public function setClosingTime(?\DateTimeInterface $closingTime): static
    {
        $this->closingTime = $closingTime;
        return $this;
    }

    public function getSlotDurationMinutes(): int
    {
        return $this->slotDurationMinutes;
    }

    public function setSlotDurationMinutes(int $slotDurationMinutes): static
    {
        $this->slotDurationMinutes = $slotDurationMinutes;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    #[Assert\Callback]
    public function validateHours(ExecutionContextInterface $context): void
    {
        if ($this->openingTime === null || $this->closingTime === null) {
            return;
        }

        if ($this->openingTime->format('H:i') >= $this->closingTime->format('H:i')) {
            $context->buildViolation('L’heure de fermeture doit être après l’heure d’ouverture.')
                ->atPath('closingTime')
                ->addViolation();
        }
    }
}
