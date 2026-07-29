<?php

namespace App\Tests\Unit;

use App\Entity\Goal;
use App\Entity\Recurrence;
use App\Entity\Transaction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Comportement du trait Timestampable, mutualisé par Transaction / Recurrence / Goal.
 *
 * Les callbacks PrePersist / PreUpdate sont ici appelés à la main : ce test vérifie la
 * logique du trait, pas leur déclenchement par Doctrine (qui relève d'un test d'intégration).
 *
 * Le provider renvoie des noms de classes et non des instances : PHPUnit résout les jeux de
 * données une seule fois pour la classe, des instances partagées fuiraient d'un test à l'autre.
 */
class TimestampableTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string<Transaction|Recurrence|Goal>}>
     */
    public static function entityProvider(): iterable
    {
        yield 'transaction' => [Transaction::class];
        yield 'recurrence' => [Recurrence::class];
        yield 'goal' => [Goal::class];
    }

    /**
     * @param class-string<Transaction|Recurrence|Goal> $class
     */
    #[DataProvider('entityProvider')]
    public function testTimestampsAreEmptyBeforePersist(string $class): void
    {
        $entity = new $class();

        $this->assertNull($entity->getCreatedAt());
        $this->assertNull($entity->getUpdatedAt());
    }

    /**
     * @param class-string<Transaction|Recurrence|Goal> $class
     */
    #[DataProvider('entityProvider')]
    public function testInitTimestampsSetsCreatedAtOnly(string $class): void
    {
        $entity = new $class();

        $entity->initTimestamps();

        $this->assertNotNull($entity->getCreatedAt());
        $this->assertNull($entity->getUpdatedAt());
    }

    /**
     * @param class-string<Transaction|Recurrence|Goal> $class
     */
    #[DataProvider('entityProvider')]
    public function testInitTimestampsIsIdempotent(string $class): void
    {
        $entity = new $class();
        $entity->initTimestamps();
        $createdAt = $entity->getCreatedAt();

        $entity->initTimestamps();

        $this->assertSame($createdAt, $entity->getCreatedAt());
    }

    /**
     * @param class-string<Transaction|Recurrence|Goal> $class
     */
    #[DataProvider('entityProvider')]
    public function testRefreshUpdatedAtSetsUpdatedAt(string $class): void
    {
        $entity = new $class();
        $entity->initTimestamps();

        $entity->refreshUpdatedAt();

        $this->assertNotNull($entity->getUpdatedAt());
        $this->assertGreaterThanOrEqual($entity->getCreatedAt(), $entity->getUpdatedAt());
    }
}
