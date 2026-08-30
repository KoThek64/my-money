<?php

namespace App\Entity;

use App\Enum\GoalScopeEnum;
use App\Interface\OwnedByUser;
use App\Repository\GoalRepository;
use App\Trait\Timestampable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: GoalRepository::class)]
// RG-10 — un seul objectif par portée. Deux index partiels plutôt qu'un
// `NULLS NOT DISTINCT`, que Doctrine ne sait pas régénérer.
#[ORM\UniqueConstraint(name: 'uniq_goal_scope_with_category', columns: ['user_id', 'type', 'category_id'], options: ['where' => '(category_id IS NOT NULL)'])]
#[ORM\UniqueConstraint(name: 'uniq_goal_scope_without_category', columns: ['user_id', 'type'], options: ['where' => '(category_id IS NULL)'])]
#[ORM\HasLifecycleCallbacks]
class Goal implements OwnedByUser
{
    use Timestampable;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(enumType: GoalScopeEnum::class)]
    private ?GoalScopeEnum $type = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Category $category = null;

    #[ORM\Column]
    private ?int $amount = null;

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

    public function getType(): ?GoalScopeEnum
    {
        return $this->type;
    }

    public function setType(GoalScopeEnum $type): static
    {
        $this->type = $type;

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
}
