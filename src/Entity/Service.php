<?php

namespace App\Entity;

use App\Repository\ServiceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ServiceRepository::class)]
#[UniqueEntity(fields: ['code'], message: 'Le code de service doit être unique.')]
#[UniqueEntity(fields: ['name'], message: 'Un service avec ce nom existe déjà.')]
class Service
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150, unique: true)]
    #[Assert\NotBlank(message: 'Le nom du service est obligatoire.')]
    #[Assert\Length(min: 3, max: 150, minMessage: 'Le nom doit comporter au moins {{ limit }} caractères.')]
    private ?string $name = null;

    #[ORM\Column(length: 10, unique: true)]
    #[Assert\NotBlank(message: 'Le code du service est obligatoire.')]
    #[Assert\Length(min: 2, max: 10, minMessage: 'Le code doit contenir au moins {{ limit }} caractères.')]
    #[Assert\Regex(pattern: '/^[A-Z0-9\-_]+$/i', message: 'Le code ne peut contenir que des lettres, chiffres et tirets.')]
    private ?string $code = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isActive = true;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\Positive(message: 'La durée estimée doit être un nombre positif supérieur à 0.')]
    private int $estimatedDurationMinutes = 15;

    /**
     * @var Collection<int, Counter>
     */
    #[ORM\OneToMany(targetEntity: Counter::class, mappedBy: 'service')]
    private Collection $counters;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->isActive = true;
        $this->estimatedDurationMinutes = 15;
        $this->counters = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = trim($name);
        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = strtoupper(trim($code));
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description ? trim($description) : null;
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

    public function getEstimatedDurationMinutes(): int
    {
        return $this->estimatedDurationMinutes;
    }

    public function setEstimatedDurationMinutes(int $estimatedDurationMinutes): static
    {
        $this->estimatedDurationMinutes = $estimatedDurationMinutes;
        return $this;
    }

    /**
     * @return Collection<int, Counter>
     */
    public function getCounters(): Collection
    {
        return $this->counters;
    }

    /**
     * @return Collection<int, Counter>
     */
    public function getActiveCounters(): Collection
    {
        return $this->counters->filter(fn(Counter $c) => $c->isActive());
    }

    public function addCounter(Counter $counter): static
    {
        if (!$this->counters->contains($counter)) {
            $this->counters->add($counter);
            $counter->setService($this);
        }
        return $this;
    }

    public function removeCounter(Counter $counter): static
    {
        if ($this->counters->removeElement($counter)) {
            if ($counter->getService() === $this) {
                $counter->setService(null);
            }
        }
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function __toString(): string
    {
        return $this->name . ' (' . $this->code . ')';
    }
}
