<?php

namespace App\Tests\Unit;

use App\Entity\Category;
use App\Entity\Recurrence;
use App\Entity\Transaction;
use App\Entity\User;
use App\Enum\MovementKindEnum;
use App\Interface\OwnedByUser;
use App\Interface\SoftDeletable;
use PHPUnit\Framework\TestCase;

class TransactionTest extends TestCase
{
    private Transaction $transaction;
    private Category $category;
    private User $user;

    protected function setUp(): void
    {
        $this->user = new User();
        $this->user->setEmail('test@example.com')
            ->setPassword('password');

        $this->category = new Category();
        $this->category->setName('Courses')
            ->setType(MovementKindEnum::DEPENSE)
            ->setIcon('icon')
            ->setColor('color')
            ->setUser($this->user);

        $this->transaction = new Transaction();
        $this->transaction->setLabel('Supermarché')
            ->setAmount(1250)
            ->setDate(new \DateTimeImmutable('2026-07-29'))
            ->setUser($this->user)
            ->setCategory($this->category);
    }

    public function testAccessors(): void
    {
        $this->assertSame('Supermarché', $this->transaction->getLabel());
        $this->assertSame(1250, $this->transaction->getAmount());
        $this->assertSame('2026-07-29', $this->transaction->getDate()?->format('Y-m-d'));
        $this->assertSame($this->user, $this->transaction->getUser());
        $this->assertSame($this->category, $this->transaction->getCategory());
    }

    public function testImplementsOwnershipAndSoftDeleteContracts(): void
    {
        $this->assertInstanceOf(OwnedByUser::class, $this->transaction);
        $this->assertInstanceOf(SoftDeletable::class, $this->transaction);
    }

    public function testIsNotDeletedByDefault(): void
    {
        $this->assertNull($this->transaction->getDeletedAt());
    }

    public function testSoftDelete(): void
    {
        $deletedAt = new \DateTimeImmutable();

        $this->transaction->setDeletedAt($deletedAt);

        $this->assertSame($deletedAt, $this->transaction->getDeletedAt());
    }

    public function testSoftDeleteCanBeUndone(): void
    {
        $this->transaction->setDeletedAt(new \DateTimeImmutable());

        $this->transaction->setDeletedAt(null);

        $this->assertNull($this->transaction->getDeletedAt());
    }

    public function testHasNoRecurrenceByDefault(): void
    {
        $this->assertNull($this->transaction->getRecurrence());
    }

    public function testRecurrenceCanBeAttachedAndDetached(): void
    {
        $recurrence = new Recurrence();

        $this->transaction->setRecurrence($recurrence);
        $this->assertSame($recurrence, $this->transaction->getRecurrence());

        // onDelete: SET NULL — la transaction survit à la suppression de sa récurrence.
        $this->transaction->setRecurrence(null);
        $this->assertNull($this->transaction->getRecurrence());
    }

    public function testCategoryChangeIsReflectedOnTheEntity(): void
    {
        $other = new Category();
        $other->setName('Loisirs')
            ->setType(MovementKindEnum::DEPENSE)
            ->setIcon('icon')
            ->setColor('color')
            ->setUser($this->user);

        $this->transaction->setCategory($other);

        $this->assertSame($other, $this->transaction->getCategory());
    }
}
