<?php

declare(strict_types=1);

namespace App\Tests\Functional\Transaction;

use App\Entity\Category;
use App\Entity\Recurrence;
use App\Entity\Transaction;
use App\Entity\User;
use App\Enum\MovementKindEnum;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Uid\Uuid;

/**
 * F01 / RG-2 / RG-3 / RG-4 — saisie et modification d'une transaction.
 */
#[Group('todo')]
final class TransactionFormTest extends WebTestCase
{
    private KernelBrowser $client;

    private User $user;

    private Uuid $groceries;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->entityManager()->getConnection()->executeStatement('DELETE FROM "user"');

        $this->user = new User();
        $this->user->setEmail('alice@my-money.test')
            ->setPassword('irrelevant');

        $this->entityManager()->persist($this->user);
        $this->entityManager()->flush();

        $this->groceries = $this->createCategory('Courses');

        $this->client->loginUser($this->user);
    }

    private function entityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        return $entityManager;
    }

    private function createCategory(
        string $name,
        MovementKindEnum $type = MovementKindEnum::DEPENSE,
        bool $archived = false,
        ?User $owner = null,
    ): Uuid {
        // Après un clear(), l'utilisateur est détaché : on repart d'une référence gérée.
        $owner = $this->entityManager()->getReference(User::class, ($owner ?? $this->user)->getId());

        $category = new Category();
        $category->setName($name)
            ->setType($type)
            ->setColor('#000000')
            ->setIcon('tabler:tag')
            ->setUser($owner);

        if ($archived) {
            $category->setArchivedAt(new \DateTimeImmutable('2026-01-15 10:00:00'));
        }

        $this->entityManager()->persist($category);
        $this->entityManager()->flush();
        $this->entityManager()->clear();

        $id = $category->getId();
        self::assertInstanceOf(Uuid::class, $id);

        return $id;
    }

    private function createTransaction(?Uuid $category = null, bool $recurring = false, bool $trashed = false): Uuid
    {
        $owner = $this->entityManager()->getReference(User::class, $this->user->getId());
        $category = $this->entityManager()->getReference(Category::class, $category ?? $this->groceries);

        $transaction = new Transaction()
            ->setUser($owner)
            ->setCategory($category)
            ->setAmount(1250)
            ->setLabel('Carrefour')
            ->setDate(new \DateTimeImmutable('2026-03-10'));

        if ($recurring) {
            $recurrence = new Recurrence()
                ->setUser($owner)
                ->setCategory($category)
                ->setAmount(1250)
                ->setLabel('Carrefour')
                ->setDayOfMonth(10);
            $this->entityManager()->persist($recurrence);
            $transaction->setRecurrence($recurrence);
        }

        if ($trashed) {
            $transaction->setDeletedAt(new \DateTimeImmutable('2026-03-11 08:00:00'));
        }

        $this->entityManager()->persist($transaction);
        $this->entityManager()->flush();
        $this->entityManager()->clear();

        $id = $transaction->getId();
        self::assertInstanceOf(Uuid::class, $id);

        return $id;
    }

    /**
     * @param array<string, string> $fields
     */
    private function submit(string $uri, array $fields): void
    {
        // Jeton « stateless » : Symfony valide l'origine de la requête.
        $this->client->request('POST', $uri, [
            'transaction' => [...$fields, '_token' => 'csrf-token'],
        ], [], ['HTTP_SEC_FETCH_SITE' => 'same-origin']);
    }

    /**
     * Ce qu'une page tierce peut envoyer : les bons champs, depuis une autre origine, sans jeton.
     *
     * @param array<string, string> $fields
     */
    private function forge(string $uri, array $fields): void
    {
        $this->client->request('POST', $uri, ['transaction' => $fields], [], ['HTTP_SEC_FETCH_SITE' => 'cross-site']);
    }

    /**
     * @param array<string, string> $override
     *
     * @return array<string, string>
     */
    private function fields(array $override = []): array
    {
        return [
            'amount' => '12,99',
            'date' => '2026-03-15',
            'category' => (string) $this->groceries,
            'label' => 'Boulangerie',
            ...$override,
        ];
    }

    /**
     * Lu en SQL : ni le filtre propriétaire ni la corbeille ne doivent masquer la ligne.
     *
     * @return array<string, mixed>
     */
    private function row(Uuid $id): array
    {
        $row = $this->entityManager()->getConnection()->fetchAssociative(
            'SELECT amount, label, date, category_id, recurrence_id, user_id, deleted_at FROM transaction WHERE id = ?',
            [(string) $id],
        );
        self::assertIsArray($row);

        return $row;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(): array
    {
        return $this->entityManager()->getConnection()->fetchAllAssociative(
            'SELECT amount, label, date, category_id, recurrence_id, user_id, deleted_at FROM transaction',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function onlyRow(): array
    {
        $rows = $this->rows();
        self::assertCount(1, $rows);

        return $rows[0];
    }

    /**
     * Garde-fou des tests qui attendent un 404 : sans la route, ils passeraient pour rien.
     */
    private function assertRouteExists(string $path): void
    {
        /** @var RouterInterface $router */
        $router = self::getContainer()->get(RouterInterface::class);
        $router->match($path);
        $this->addToAssertionCount(1);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function creationTabs(): iterable
    {
        yield 'dépense par défaut' => ['/transaction/create', 'depense'];
        yield 'onglet dépense' => ['/transaction/create/depense', 'depense'];
        yield 'onglet revenu' => ['/transaction/create/revenu', 'revenu'];
    }

    /**
     * RG-3 — la nature de la transaction vient de sa catégorie ; RG-1.3 — rattachée au compte connecté.
     */
    #[DataProvider('creationTabs')]
    public function testValidCreationIsOwnedByTheLoggedInUser(string $uri, string $type): void
    {
        $category = 'revenu' === $type ? $this->createCategory('Salaire', MovementKindEnum::REVENU) : $this->groceries;

        $this->submit($uri, $this->fields(['category' => (string) $category]));

        self::assertResponseRedirects('/');
        $row = $this->onlyRow();
        self::assertSame(1299, $row['amount']);
        self::assertSame('2026-03-15', $row['date']);
        self::assertSame('Boulangerie', $row['label']);
        self::assertSame((string) $category, $row['category_id']);
        self::assertSame((string) $this->user->getId(), $row['user_id']);
        self::assertNull($row['recurrence_id']);
        self::assertNull($row['deleted_at']);
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function validAmounts(): iterable
    {
        yield 'virgule' => ['12,99', 1299];
        // Le clavier numérique d'un téléphone donne souvent un point.
        yield 'point' => ['12.99', 1299];
        yield 'entier' => ['15', 1500];
        yield 'un centime' => ['0,01', 1];
        yield 'limite de la colonne' => ['21474836,47', 2147483647];
    }

    /**
     * RG-2 — saisi en euros, stocké en centimes entiers.
     */
    #[DataProvider('validAmounts')]
    public function testAmountIsStoredInCents(string $amount, int $cents): void
    {
        $this->submit('/transaction/create', $this->fields(['amount' => $amount]));

        self::assertResponseRedirects('/');
        self::assertSame($cents, $this->onlyRow()['amount']);
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function invalidFields(): iterable
    {
        yield 'montant nul' => [['amount' => '0']];
        yield 'montant nul à deux décimales' => [['amount' => '0,00']];
        yield 'montant négatif' => [['amount' => '-5']];
        yield 'montant vide' => [['amount' => '']];
        yield 'montant non numérique' => [['amount' => 'abc']];
        yield 'montant au-delà de la colonne' => [['amount' => '21474836,48']];
        // Arrondir changerait le montant sans prévenir.
        yield 'montant à trois décimales' => [['amount' => '12,999']];
        yield 'date vide' => [['date' => '']];
        yield 'date invalide' => [['date' => 'pas-une-date']];
        yield 'catégorie absente' => [['category' => '']];
        yield 'catégorie inexistante' => [['category' => '0197c3e0-0000-7000-8000-000000000001']];
        yield 'libellé trop long' => [['label' => str_repeat('a', 121)]];
    }

    /**
     * @param array<string, string> $override
     */
    #[DataProvider('invalidFields')]
    public function testInvalidCreationIsRejected(array $override): void
    {
        $this->submit('/transaction/create', $this->fields($override));

        self::assertResponseStatusCodeSame(422);
        self::assertSame([], $this->rows());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function unavailableCategories(): iterable
    {
        yield 'catégorie de revenu sur l\'onglet dépense' => ['/transaction/create', 'income'];
        yield 'catégorie de dépense sur l\'onglet revenu' => ['/transaction/create/revenu', 'expense'];
        yield 'catégorie archivée' => ['/transaction/create', 'archived'];
        yield 'catégorie d\'un autre compte' => ['/transaction/create', 'other-account'];
    }

    /**
     * RG-3 / RG-4 / RG-1 — seule une catégorie active du compte, du type de l'onglet, est acceptée,
     * même si on force son uuid dans la requête.
     */
    #[DataProvider('unavailableCategories')]
    public function testUnavailableCategoryIsRejectedOnCreation(string $uri, string $scenario): void
    {
        $category = match ($scenario) {
            'income' => $this->createCategory('Salaire', MovementKindEnum::REVENU),
            'expense' => $this->groceries,
            'archived' => $this->createCategory('Ancienne salle de sport', archived: true),
            'other-account' => $this->createCategory('Courses de Bob', owner: $this->createOtherUser()),
            default => throw new \LogicException($scenario),
        };

        $this->submit($uri, $this->fields(['category' => (string) $category]));

        self::assertResponseStatusCodeSame(422);
        self::assertSame([], $this->rows());
    }

    private function createOtherUser(): User
    {
        $bob = new User();
        $bob->setEmail('bob@my-money.test')
            ->setPassword('irrelevant');

        $this->entityManager()->persist($bob);
        $this->entityManager()->flush();

        return $bob;
    }

    public function testUnknownTabIsNotFound(): void
    {
        $this->assertRouteExists('/transaction/create/revenu');

        $this->client->request('GET', '/transaction/create/epargne');

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * RG-3b — une dépense prévue peut être saisie à l'avance.
     */
    public function testFutureDateIsAccepted(): void
    {
        $future = new \DateTimeImmutable('+1 month')->format('Y-m-d');

        $this->submit('/transaction/create', $this->fields(['date' => $future]));

        self::assertResponseRedirects('/');
        self::assertSame($future, $this->onlyRow()['date']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function blankLabels(): iterable
    {
        yield 'vide' => [''];
        yield 'espaces seulement' => ['   '];
    }

    /**
     * Friction minimale : le libellé est facultatif.
     */
    #[DataProvider('blankLabels')]
    public function testLabelIsOptional(string $label): void
    {
        $this->submit('/transaction/create', $this->fields(['label' => $label]));

        self::assertResponseRedirects('/');
        self::assertNull($this->onlyRow()['label']);
    }

    public function testLabelAtColumnLimitIsAccepted(): void
    {
        $this->submit('/transaction/create', $this->fields(['label' => str_repeat('a', 120)]));

        self::assertResponseRedirects('/');
        self::assertSame(str_repeat('a', 120), $this->onlyRow()['label']);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function protectedFields(): iterable
    {
        yield 'propriétaire' => ['user', 'another-user'];
        yield 'récurrence' => ['recurrence', '0197c3e0-0000-7000-8000-000000000001'];
        yield 'date de mise en corbeille' => ['deletedAt', '2026-03-15 10:00:00'];
    }

    /**
     * RG-1.3 — le propriétaire ne vient jamais du formulaire ; on ne s'accroche pas non plus
     * à une récurrence ni ne crée directement en corbeille.
     */
    #[DataProvider('protectedFields')]
    public function testProtectedFieldCannotBeSubmitted(string $field, string $value): void
    {
        $this->submit('/transaction/create', $this->fields([$field => $value]));

        self::assertResponseStatusCodeSame(422);
        self::assertSame([], $this->rows());
    }

    /**
     * CSRF — une page tierce ne doit pas pouvoir saisir une transaction au nom de l'utilisateur.
     */
    public function testForgedCreationIsRefused(): void
    {
        $this->forge('/transaction/create', $this->fields());

        self::assertResponseStatusCodeSame(422);
        self::assertSame([], $this->rows());
    }

    /**
     * RG-3 — la date est pré-remplie à aujourd'hui, modifiable.
     */
    #[Group('template')]
    public function testDateDefaultsToToday(): void
    {
        $crawler = $this->client->request('GET', '/transaction/create');

        self::assertResponseIsSuccessful();
        self::assertSame(
            new \DateTimeImmutable()->format('Y-m-d'),
            $crawler->filter('[name="transaction[date]"]')->attr('value'),
        );
    }

    /**
     * RG-3 / RG-4 — le menu ne propose que les catégories actives du compte, du type de l'onglet.
     */
    #[Group('template')]
    public function testCategoryMenuOffersActiveCategoriesOfTheTabTypeOnly(): void
    {
        $rent = $this->createCategory('Loyer');
        $salary = $this->createCategory('Salaire', MovementKindEnum::REVENU);
        $archived = $this->createCategory('Ancienne salle de sport', archived: true);
        $bobs = $this->createCategory('Courses de Bob', owner: $this->createOtherUser());

        $expenseMenu = $this->offeredCategories('/transaction/create');
        $incomeMenu = $this->offeredCategories('/transaction/create/revenu');

        self::assertEqualsCanonicalizing([(string) $this->groceries, (string) $rent], $expenseMenu);
        self::assertSame([(string) $salary], $incomeMenu);
        self::assertNotContains((string) $archived, [...$expenseMenu, ...$incomeMenu]);
        self::assertNotContains((string) $bobs, [...$expenseMenu, ...$incomeMenu]);
    }

    /**
     * Liste déroulante ou grille de boutons radio : on lit les valeurs proposées, pas le rendu.
     *
     * @return list<string>
     */
    private function offeredCategories(string $uri): array
    {
        $crawler = $this->client->request('GET', $uri);
        self::assertResponseIsSuccessful();

        $choices = $crawler->filter('select[name="transaction[category]"] option, input[name="transaction[category]"]');

        return array_values(array_filter(
            $choices->each(static fn ($choice): string => (string) $choice->attr('value')),
            static fn (string $value): bool => '' !== $value,
        ));
    }

    /**
     * RG-3 — montant, date, catégorie et libellé restent modifiables.
     */
    public function testValidEditUpdatesEveryField(): void
    {
        $id = $this->createTransaction();
        $rent = $this->createCategory('Loyer');

        $this->submit('/transaction/edit/'.$id, [
            'amount' => '20,00',
            'date' => '2026-04-02',
            'category' => (string) $rent,
            'label' => 'Loyer d\'avril',
        ]);

        self::assertResponseRedirects('/');
        $row = $this->row($id);
        self::assertSame(2000, $row['amount']);
        self::assertSame('2026-04-02', $row['date']);
        self::assertSame((string) $rent, $row['category_id']);
        self::assertSame('Loyer d\'avril', $row['label']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidEdits(): iterable
    {
        // Pas de passage dépense → revenu en modification : le menu ne propose que le même type.
        yield 'catégorie de l\'autre type' => ['income'];
        yield 'autre catégorie archivée' => ['archived'];
        yield 'catégorie d\'un autre compte' => ['other-account'];
        yield 'montant nul' => ['zero'];
        yield 'date vide' => ['no-date'];
    }

    #[DataProvider('invalidEdits')]
    public function testInvalidEditLeavesTheTransactionUntouched(string $scenario): void
    {
        $id = $this->createTransaction();

        $fields = match ($scenario) {
            'income' => ['category' => (string) $this->createCategory('Salaire', MovementKindEnum::REVENU)],
            'archived' => ['category' => (string) $this->createCategory('Ancienne salle de sport', archived: true)],
            'other-account' => ['category' => (string) $this->createCategory('Courses de Bob', owner: $this->createOtherUser())],
            'zero' => ['amount' => '0'],
            'no-date' => ['date' => ''],
            default => throw new \LogicException($scenario),
        };

        $this->submit('/transaction/edit/'.$id, $this->fields(['amount' => '20,00', ...$fields]));

        self::assertResponseStatusCodeSame(422);
        $row = $this->row($id);
        self::assertSame(1250, $row['amount']);
        self::assertSame('2026-03-10', $row['date']);
        self::assertSame((string) $this->groceries, $row['category_id']);
    }

    /**
     * RG-4 — une transaction garde sa catégorie archivée depuis : on doit pouvoir corriger le reste.
     */
    public function testArchivedCurrentCategoryCanBeKept(): void
    {
        $id = $this->createTransaction();

        $this->archiveCategory($this->groceries);

        $this->submit('/transaction/edit/'.$id, $this->fields(['amount' => '20,00']));

        self::assertResponseRedirects('/');
        $row = $this->row($id);
        self::assertSame(2000, $row['amount']);
        self::assertSame((string) $this->groceries, $row['category_id']);
    }

    private function archiveCategory(Uuid $id): void
    {
        $category = $this->entityManager()->find(Category::class, $id);
        self::assertInstanceOf(Category::class, $category);
        $category->setArchivedAt(new \DateTimeImmutable('2026-03-12 09:00:00'));
        $this->entityManager()->flush();
        $this->entityManager()->clear();
    }

    /**
     * RG-3 — modifier une occurrence ne la détache pas de sa récurrence.
     */
    public function testEditedOccurrenceStaysAttachedToItsRecurrence(): void
    {
        $id = $this->createTransaction(recurring: true);
        $recurrence = $this->row($id)['recurrence_id'];
        self::assertNotNull($recurrence);

        $this->submit('/transaction/edit/'.$id, $this->fields(['amount' => '20,00']));

        self::assertResponseRedirects('/');
        $row = $this->row($id);
        self::assertSame(2000, $row['amount']);
        self::assertSame($recurrence, $row['recurrence_id']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function editRequests(): iterable
    {
        yield 'formulaire de modification' => ['GET'];
        yield 'modification' => ['POST'];
    }

    /**
     * RG-7 — une transaction en corbeille se restaure, elle ne se modifie pas.
     */
    #[DataProvider('editRequests')]
    public function testTrashedTransactionCannotBeEdited(string $method): void
    {
        $id = $this->createTransaction(trashed: true);
        $this->assertRouteExists('/transaction/edit/'.$id);

        if ('POST' === $method) {
            $this->submit('/transaction/edit/'.$id, $this->fields(['amount' => '20,00']));
        } else {
            $this->client->request('GET', '/transaction/edit/'.$id);
        }

        self::assertResponseStatusCodeSame(404);
        self::assertSame(1250, $this->row($id)['amount']);
    }

    public function testForgedEditIsRefused(): void
    {
        $id = $this->createTransaction();

        $this->forge('/transaction/edit/'.$id, $this->fields(['amount' => '999,00']));

        self::assertResponseStatusCodeSame(422);
        self::assertSame(1250, $this->row($id)['amount']);
    }
}
