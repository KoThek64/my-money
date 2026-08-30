<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Verrouille en base les garanties qu'un `make:migration` à l'aveugle ferait sauter :
 * CHECK amount > 0, CASCADE des FK user_id, unicité des objectifs.
 */
final class DatabaseHardeningTest extends KernelTestCase
{
    private static function connection(): Connection
    {
        self::bootKernel();

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');

        return $connection;
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function amountCheckProvider(): iterable
    {
        yield 'transaction' => ['transaction', 'chk_tx_amount_positive'];
        yield 'recurrence' => ['recurrence', 'chk_rec_amount_positive'];
        yield 'goal' => ['goal', 'chk_goal_amount_positive'];
    }

    /**
     * RG-2 — les montants sont toujours strictement positifs ; le sens
     * (dépense / revenu) est porté par category.type, jamais par le signe.
     */
    #[DataProvider('amountCheckProvider')]
    public function testAmountIsConstrainedToBeStrictlyPositive(string $table, string $constraint): void
    {
        $definition = self::connection()->fetchOne(
            <<<'SQL'
                SELECT pg_get_constraintdef(oid)
                FROM pg_constraint
                WHERE contype = 'c'
                  AND connamespace = 'public'::regnamespace
                  AND conrelid = ?::regclass
                  AND conname = ?
                SQL,
            [$table, $constraint],
        );

        self::assertIsString(
            $definition,
            \sprintf('Le CHECK "%s" a disparu de la table "%s" — une migration l\'a probablement écrasé.', $constraint, $table),
        );
        self::assertStringContainsString('amount > 0', $definition);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function userOwnedTableProvider(): iterable
    {
        yield 'category' => ['category'];
        yield 'recurrence' => ['recurrence'];
        yield 'transaction' => ['transaction'];
        yield 'goal' => ['goal'];
    }

    /**
     * F10 — supprimer un compte doit emporter ses données. Sans ON DELETE,
     * Postgres applique RESTRICT et la suppression devient impossible.
     */
    #[DataProvider('userOwnedTableProvider')]
    public function testUserForeignKeyCascadesOnDelete(string $table): void
    {
        $definition = self::connection()->fetchOne(
            <<<'SQL'
                SELECT pg_get_constraintdef(c.oid)
                FROM pg_constraint c
                JOIN pg_attribute a ON a.attrelid = c.conrelid AND a.attnum = c.conkey[1]
                WHERE c.contype = 'f'
                  AND c.conrelid = ?::regclass
                  AND c.confrelid = 'public."user"'::regclass
                  AND a.attname = 'user_id'
                SQL,
            [$table],
        );

        self::assertIsString($definition, \sprintf('Aucune FK user_id trouvée sur "%s".', $table));
        self::assertStringContainsString(
            'ON DELETE CASCADE',
            $definition,
            \sprintf('La FK user_id de "%s" n\'est plus en CASCADE : la suppression de compte est cassée.', $table),
        );
    }

    private function insertUser(Connection $connection): string
    {
        $id = Uuid::v7()->toRfc4122();

        $connection->executeStatement(
            'INSERT INTO "user" (id, email, roles, password, created_at) VALUES (?, ?, ?, ?, NOW())',
            [$id, \sprintf('%s@my-money.test', $id), '[]', 'irrelevant'],
        );

        return $id;
    }

    private function insertCategory(Connection $connection, string $userId): string
    {
        $id = Uuid::v7()->toRfc4122();

        $connection->executeStatement(
            'INSERT INTO category (id, user_id, name, type, color, icon, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())',
            [$id, $userId, 'Courses', 'depense', '#000000', 'cart'],
        );

        return $id;
    }

    private function insertGoal(Connection $connection, string $userId, string $type, ?string $categoryId): void
    {
        $connection->executeStatement(
            'INSERT INTO goal (id, user_id, type, category_id, amount, created_at) VALUES (?, ?, ?, ?, ?, NOW())',
            [Uuid::v7()->toRfc4122(), $userId, $type, $categoryId, 10_000],
        );
    }

    /**
     * RG-10 — cas piégeux : en unicité SQL classique NULL ≠ NULL, donc « global »
     * et « épargne » seraient duplicables à l'infini.
     *
     * @return iterable<string, array{string}>
     */
    public static function nullCategoryScopeProvider(): iterable
    {
        yield 'épargne' => ['epargne'];
        yield 'dépense globale' => ['depense_globale'];
    }

    #[DataProvider('nullCategoryScopeProvider')]
    public function testGoalWithoutCategoryCannotBeDuplicated(string $scope): void
    {
        $connection = self::connection();
        $connection->executeStatement('DELETE FROM "user"');
        $userId = $this->insertUser($connection);

        $this->insertGoal($connection, $userId, $scope, null);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->insertGoal($connection, $userId, $scope, null);
    }

    public function testGoalOnTheSameCategoryCannotBeDuplicated(): void
    {
        $connection = self::connection();
        $connection->executeStatement('DELETE FROM "user"');
        $userId = $this->insertUser($connection);
        $categoryId = $this->insertCategory($connection, $userId);

        $this->insertGoal($connection, $userId, 'depense_categorie', $categoryId);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->insertGoal($connection, $userId, 'depense_categorie', $categoryId);
    }

    /**
     * Pendant du test précédent : l'unicité ne doit pas être trop large (RG-1).
     */
    public function testGoalsOnDistinctCategoriesAndUsersAreAllowed(): void
    {
        $connection = self::connection();
        $connection->executeStatement('DELETE FROM "user"');

        $userId = $this->insertUser($connection);
        $this->insertGoal($connection, $userId, 'depense_categorie', $this->insertCategory($connection, $userId));
        $this->insertGoal($connection, $userId, 'depense_categorie', $this->insertCategory($connection, $userId));
        $this->insertGoal($connection, $userId, 'epargne', null);

        $otherUserId = $this->insertUser($connection);
        $this->insertGoal($connection, $otherUserId, 'epargne', null);

        self::assertSame(4, (int) $connection->fetchOne('SELECT COUNT(*) FROM goal'));
    }

    /**
     * F10 — la suppression du compte doit emporter ses données, en une requête.
     */
    public function testDeletingAUserCascadesToItsData(): void
    {
        $connection = self::connection();
        $connection->executeStatement('DELETE FROM "user"');

        $userId = $this->insertUser($connection);
        $this->insertGoal($connection, $userId, 'epargne', null);
        $this->insertCategory($connection, $userId);

        $connection->executeStatement('DELETE FROM "user" WHERE id = ?', [$userId]);

        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM goal'));
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM category'));
    }
}
