<?php

namespace App\Tests\Unit;

use App\Entity\Category;
use App\Entity\Goal;
use App\Entity\User;
use App\Enum\GoalScopeEnum;
use App\Enum\MovementKindEnum;
use App\Interface\OwnedByUser;
use PHPUnit\Framework\TestCase;

class GoalTest extends TestCase
{
    private Goal $goal;
    private User $user;

    protected function setUp(): void
    {
        $this->user = new User();
        $this->user->setEmail('test@example.com')
            ->setPassword('password');

        $this->goal = new Goal();
        $this->goal->setType(GoalScopeEnum::DEPENSE_GLOBALE)
            ->setAmount(100000)
            ->setUser($this->user);
    }

    public function testAccessors(): void
    {
        $this->assertSame(GoalScopeEnum::DEPENSE_GLOBALE, $this->goal->getType());
        $this->assertSame(100000, $this->goal->getAmount());
        $this->assertSame($this->user, $this->goal->getUser());
    }

    public function testImplementsOwnershipContract(): void
    {
        $this->assertInstanceOf(OwnedByUser::class, $this->goal);
    }

    public function testGlobalGoalHasNoCategory(): void
    {
        $this->assertNull($this->goal->getCategory());
    }

    public function testCategoryScopedGoal(): void
    {
        $category = new Category();
        $category->setName('Courses')
            ->setType(MovementKindEnum::DEPENSE)
            ->setIcon('icon')
            ->setColor('color')
            ->setUser($this->user);

        $this->goal->setType(GoalScopeEnum::DEPENSE_CATEGORIE)
            ->setCategory($category);

        $this->assertSame(GoalScopeEnum::DEPENSE_CATEGORIE, $this->goal->getType());
        $this->assertSame($category, $this->goal->getCategory());
    }

    public function testCategoryCanBeDetached(): void
    {
        $category = new Category();
        $category->setName('Courses')
            ->setType(MovementKindEnum::DEPENSE)
            ->setIcon('icon')
            ->setColor('color')
            ->setUser($this->user);
        $this->goal->setCategory($category);

        // onDelete: SET NULL — l'objectif survit à la suppression de sa catégorie.
        $this->goal->setCategory(null);

        $this->assertNull($this->goal->getCategory());
    }
}
