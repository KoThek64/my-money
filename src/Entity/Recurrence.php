<?php

namespace App\Entity;

use App\Interface\OwnedByUser;
use App\Repository\RecurrenceRepository;
use App\Trait\Timestampable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: RecurrenceRepository::class)]
#[ORM\Index(columns: ['user_id'])]
#[ORM\HasLifecycleCallbacks]
class Recurrence implements OwnedByUser
{
    use Timestampable;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Category $category = null;

    #[ORM\Column]
    private ?int $amount = null;

    #[ORM\Column(length: 120)]
    private ?string $label = null;

    #[ORM\Column]
    private ?int $dayOfMonth = null;

    /**
     * Format (YYYY-MM).
     */
    #[ORM\Column(length: 7, nullable: true)]
    private ?string $lastGeneratedMonth = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $stoppedAt = null;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getAmount(): ?int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getDayOfMonth(): ?int
    {
        return $this->dayOfMonth;
    }

    public function setDayOfMonth(int $dayOfMonth): static
    {
        $this->dayOfMonth = $dayOfMonth;

        return $this;
    }

    public function getLastGeneratedMonth(): ?string
    {
        return $this->lastGeneratedMonth;
    }

    public function setLastGeneratedMonth(?string $lastGeneratedMonth): static
    {
        $this->lastGeneratedMonth = $lastGeneratedMonth;

        return $this;
    }

    public function getStoppedAt(): ?\DateTimeImmutable
    {
        return $this->stoppedAt;
    }

    public function setStoppedAt(?\DateTimeImmutable $stoppedAt): static
    {
        $this->stoppedAt = $stoppedAt;

        return $this;
    }
}
