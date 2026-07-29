<?php

namespace App\Tests\Unit;

use App\Entity\Category;
use App\Entity\Recurrence;
use App\Entity\User;
use App\Enum\MovementKindEnum;
use App\Interface\OwnedByUser;
use PHPUnit\Framework\TestCase;

class RecurrenceTest extends TestCase
{
    private Recurrence $recurrence;
    private Category $category;
    private User $user;

    protected function setUp(): void
    {
        $this->user = new User();
        $this->user->setEmail('test@example.com')
            ->setPassword('password');

        $this->category = new Category();
        $this->category->setName('Loyer')
            ->setType(MovementKindEnum::DEPENSE)
            ->setIcon('icon')
            ->setColor('color')
            ->setUser($this->user);

        $this->recurrence = new Recurrence();
        $this->recurrence->setLabel('Loyer mensuel')
            ->setAmount(75000)
            ->setDayOfMonth(5)
            ->setUser($this->user)
            ->setCategory($this->category);
    }

    public function testAccessors(): void
    {
        $this->assertSame('Loyer mensuel', $this->recurrence->getLabel());
        $this->assertSame(75000, $this->recurrence->getAmount());
        $this->assertSame(5, $this->recurrence->getDayOfMonth());
        $this->assertSame($this->user, $this->recurrence->getUser());
        $this->assertSame($this->category, $this->recurrence->getCategory());
    }

    public function testImplementsOwnershipContract(): void
    {
        $this->assertInstanceOf(OwnedByUser::class, $this->recurrence);
    }

    public function testNothingGeneratedYet(): void
    {
        $this->assertNull($this->recurrence->getLastGeneratedMonth());
    }

    public function testLastGeneratedMonthUsesYearMonthFormat(): void
    {
        $this->recurrence->setLastGeneratedMonth('2026-07');

        $this->assertSame('2026-07', $this->recurrence->getLastGeneratedMonth());
    }

    public function testLastGeneratedMonthCanBeReset(): void
    {
        $this->recurrence->setLastGeneratedMonth('2026-07');

        $this->recurrence->setLastGeneratedMonth(null);

        $this->assertNull($this->recurrence->getLastGeneratedMonth());
    }

    public function testIsActiveByDefault(): void
    {
        $this->assertNull($this->recurrence->getStoppedAt());
    }

    public function testCanBeStoppedAndRestarted(): void
    {
        $stoppedAt = new \DateTimeImmutable();

        $this->recurrence->setStoppedAt($stoppedAt);
        $this->assertSame($stoppedAt, $this->recurrence->getStoppedAt());

        $this->recurrence->setStoppedAt(null);
        $this->assertNull($this->recurrence->getStoppedAt());
    }
}
