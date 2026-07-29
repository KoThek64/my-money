<?php

namespace App\Tests\Unit;

use App\Entity\Category;
use App\Entity\Transaction;
use App\Entity\User;
use App\Enum\MovementKindEnum;
use PHPUnit\Framework\TestCase;

class CategoryTest extends TestCase
{
    private Category $category;

    protected function setUp(): void
    {
        $user = new User();
        $user->setEmail('test@example.com')
            ->setPassword('password');

        $this->category = new Category();
        $this->category->setName('Test Category')
            ->setType(MovementKindEnum::DEPENSE)
            ->setIcon('icon')
            ->setColor('color')
            ->setUser($user);
    }

    public function testAccessors(): void
    {
        $this->assertSame('Test Category', $this->category->getName());
        $this->assertSame(MovementKindEnum::DEPENSE, $this->category->getType());
        $this->assertSame('icon', $this->category->getIcon());
        $this->assertSame('color', $this->category->getColor());
        $this->assertSame('test@example.com', $this->category->getUser()?->getEmail());
    }

    public function testCreatedAtIsSetOnConstruction(): void
    {
        $this->assertNotNull($this->category->getCreatedAt());
    }

    public function testIsNotArchivedByDefault(): void
    {
        $this->assertNull($this->category->getArchivedAt());
    }

    public function testAddTransactionSetsBothSides(): void
    {
        $transaction = new Transaction();

        $this->category->addTransaction($transaction);

        $this->assertCount(1, $this->category->getTransactions());
        $this->assertTrue($this->category->getTransactions()->contains($transaction));
        $this->assertSame($this->category, $transaction->getCategory());
    }

    public function testAddTransactionTwiceDoesNotDuplicate(): void
    {
        $transaction = new Transaction();

        $this->category->addTransaction($transaction);
        $this->category->addTransaction($transaction);

        $this->assertCount(1, $this->category->getTransactions());
    }

    public function testRemoveTransactionDetachesIt(): void
    {
        $transaction = new Transaction();
        $this->category->addTransaction($transaction);

        $this->category->removeTransaction($transaction);

        $this->assertCount(0, $this->category->getTransactions());
        $this->assertNull($transaction->getCategory());
    }
}
