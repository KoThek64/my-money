<?php

declare(strict_types=1);

namespace App\Tests\Functional\Category;

use App\Entity\Category;
use App\Entity\Transaction;
use App\Entity\User;
use App\Enum\MovementKindEnum;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * F02 / RG-4 — création, modification et liste des catégories.
 */
final class CategoryFormTest extends WebTestCase
{
    private const array VALID = [
        'name' => 'Courses',
        'type' => 'depense',
        'color' => '#1a2b3c',
        'icon' => 'tabler:cart',
    ];

    private KernelBrowser $client;

    private User $user;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->entityManager()->getConnection()->executeStatement('DELETE FROM "user"');

        $this->user = new User();
        $this->user->setEmail('alice@my-money.test')
            ->setPassword('irrelevant');

        $this->entityManager()->persist($this->user);
        $this->entityManager()->flush();

        $this->client->loginUser($this->user);
    }

    private function entityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        return $entityManager;
    }

    private function createCategory(
        bool $used = false,
        bool $archived = false,
        string $name = 'Loyer',
        ?User $owner = null,
    ): Uuid {
        // Après un clear(), l'utilisateur est détaché : on repart d'une référence gérée.
        $owner = $this->entityManager()->getReference(User::class, ($owner ?? $this->user)->getId());

        $category = new Category();
        $category->setName($name)
            ->setType(MovementKindEnum::DEPENSE)
            ->setColor('#000000')
            ->setIcon('tabler:home')
            ->setUser($owner);

        if ($archived) {
            $category->setArchivedAt(new \DateTimeImmutable('2026-01-15 10:00:00'));
        }

        $this->entityManager()->persist($category);

        if ($used) {
            $this->entityManager()->persist(new Transaction()
                ->setUser($owner)
                ->setCategory($category)
                ->setAmount(1000)
                ->setLabel('Loyer de mars')
                ->setDate(new \DateTimeImmutable()));
        }

        $this->entityManager()->flush();
        $this->entityManager()->clear();

        $id = $category->getId();
        self::assertInstanceOf(Uuid::class, $id);

        return $id;
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

    private function reload(Uuid $id): Category
    {
        // Le filtre reste armé au nom d'Alice après une requête : il masquerait les lignes de Bob.
        $filters = $this->entityManager()->getFilters();
        if ($filters->isEnabled('owner')) {
            $filters->disable('owner');
        }

        $this->entityManager()->clear();
        $category = $this->entityManager()->find(Category::class, $id);
        self::assertInstanceOf(Category::class, $category);

        return $category;
    }

    /**
     * @param array<string, string> $fields
     */
    private function submit(string $uri, string $formName, array $fields): void
    {
        // Jeton « stateless » : Symfony valide l'origine de la requête.
        $this->client->request('POST', $uri, [
            $formName => [...$fields, '_token' => 'csrf-token'],
        ], [], ['HTTP_SEC_FETCH_SITE' => 'same-origin']);
    }

    /**
     * Ce qu'une page tierce peut envoyer : les bons champs, depuis une autre origine, sans jeton.
     *
     * @param array<string, string> $fields
     */
    private function forge(string $uri, string $formName, array $fields): void
    {
        $this->client->request('POST', $uri, [$formName => $fields], [], ['HTTP_SEC_FETCH_SITE' => 'cross-site']);
    }

    private function countCategories(): int
    {
        // En SQL : le filtre propriétaire ne doit pas fausser le décompte.
        return (int) $this->entityManager()->getConnection()->fetchOne('SELECT COUNT(*) FROM category');
    }

    public function testValidCreationIsOwnedByTheLoggedInUser(): void
    {
        $this->submit('/category/create', 'category', self::VALID);

        self::assertResponseRedirects();

        $category = $this->entityManager()->getRepository(Category::class)->findOneBy(['name' => 'Courses']);
        self::assertInstanceOf(Category::class, $category);
        self::assertSame(MovementKindEnum::DEPENSE, $category->getType());
        self::assertSame('#1a2b3c', $category->getColor());
        self::assertSame('tabler:cart', $category->getIcon());
        self::assertEquals($this->user->getId(), $category->getUser()?->getId());
    }

    public function testIncomeCategoryCanBeCreated(): void
    {
        $this->submit('/category/create', 'category', [...self::VALID, 'name' => 'Salaire', 'type' => 'revenu']);

        self::assertResponseRedirects();

        $category = $this->entityManager()->getRepository(Category::class)->findOneBy(['name' => 'Salaire']);
        self::assertInstanceOf(Category::class, $category);
        self::assertSame(MovementKindEnum::REVENU, $category->getType());
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function invalidFields(): iterable
    {
        yield 'nom vide' => [['name' => '']];
        yield 'nom trop long' => [['name' => str_repeat('a', 61)]];
        yield 'type absent' => [['type' => '']];
        yield 'type inconnu' => [['type' => 'epargne']];
        yield 'couleur vide' => [['color' => '']];
        yield 'couleur hors format' => [['color' => 'rouge']];
        yield 'icône vide' => [['icon' => '']];
        yield 'icône trop longue' => [['icon' => str_repeat('b', 61)]];
    }

    /**
     * @param array<string, string> $override
     */
    #[DataProvider('invalidFields')]
    public function testInvalidCreationIsRejected(array $override): void
    {
        $this->submit('/category/create', 'category', [...self::VALID, ...$override]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->countCategories());
    }

    public function testNameAtColumnLimitIsAccepted(): void
    {
        $this->submit('/category/create', 'category', [...self::VALID, 'name' => str_repeat('a', 60)]);

        self::assertResponseRedirects();
        self::assertSame(1, $this->countCategories());
    }

    /**
     * RG-1.3 — le propriétaire ne vient jamais du formulaire.
     */
    public function testOwnerCannotBeSubmitted(): void
    {
        $this->submit('/category/create', 'category', [...self::VALID, 'user' => 'another-user']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->countCategories());
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function invalidEditFields(): iterable
    {
        yield 'nom vide' => [['name' => '']];
        yield 'nom trop long' => [['name' => str_repeat('a', 61)]];
        yield 'couleur vide' => [['color' => '']];
        yield 'couleur hors format' => [['color' => 'rouge']];
        yield 'icône vide' => [['icon' => '']];
        yield 'icône trop longue' => [['icon' => str_repeat('b', 61)]];
    }

    /**
     * @param array<string, string> $override
     */
    #[DataProvider('invalidEditFields')]
    public function testInvalidEditLeavesTheCategoryUntouched(array $override): void
    {
        $id = $this->createCategory();

        $this->submit('/category/edit/'.$id, 'category_edit', [
            'name' => 'Logement',
            'type' => 'depense',
            'color' => '#ffffff',
            'icon' => 'tabler:building',
            ...$override,
        ]);

        self::assertResponseStatusCodeSame(422);
        $category = $this->reload($id);
        self::assertSame('Loyer', $category->getName());
        self::assertSame('#000000', $category->getColor());
        self::assertSame('tabler:home', $category->getIcon());
    }

    /**
     * RG-4 — nom, couleur et icône restent éditables même une fois la catégorie utilisée.
     */
    public function testUsedCategoryKeepsNameColorAndIconEditable(): void
    {
        $id = $this->createCategory(used: true);

        $this->submit('/category/edit/'.$id, 'category_edit', ['name' => 'Logement', 'color' => '#ffffff', 'icon' => 'tabler:building']);

        self::assertResponseRedirects();
        $category = $this->reload($id);
        self::assertSame('Logement', $category->getName());
        self::assertSame('#ffffff', $category->getColor());
        self::assertSame('tabler:building', $category->getIcon());
    }

    /**
     * RG-4 — le type reste éditable tant que rien n'utilise la catégorie.
     */
    public function testUnusedCategoryTypeIsEditable(): void
    {
        $id = $this->createCategory();

        $this->submit('/category/edit/'.$id, 'category_edit', ['name' => 'Loyer', 'type' => 'revenu', 'color' => '#000000', 'icon' => 'tabler:home']);

        self::assertResponseRedirects();
        self::assertSame(MovementKindEnum::REVENU, $this->reload($id)->getType());
    }

    /**
     * RG-4 — changer le type réécrirait le sens de tout l'historique.
     */
    public function testUsedCategoryTypeIsFrozen(): void
    {
        $id = $this->createCategory(used: true);

        $this->submit('/category/edit/'.$id, 'category_edit', ['name' => 'Loyer', 'type' => 'revenu', 'color' => '#000000', 'icon' => 'tabler:home']);

        self::assertSame(MovementKindEnum::DEPENSE, $this->reload($id)->getType());
    }

    /**
     * Les archivées se consultent ailleurs (paramètres), pas dans la liste de gestion.
     */
    #[Group('todo')]
    public function testListShowsActiveCategoriesOnly(): void
    {
        $this->createCategory(name: 'Loyer');
        $this->createCategory(archived: true, name: 'Ancienne salle de sport');

        $this->client->request('GET', '/category/show-all');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Loyer');
        self::assertSelectorTextNotContains('body', 'Ancienne salle de sport');
    }

    /**
     * CSRF — une page tierce ne doit pas pouvoir créer une catégorie au nom de l'utilisateur.
     */
    public function testForgedCreationIsRefused(): void
    {
        $this->forge('/category/create', 'category', self::VALID);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->countCategories());
    }

    public function testForgedEditIsRefused(): void
    {
        $id = $this->createCategory();

        $this->forge('/category/edit/'.$id, 'category_edit', ['name' => 'Piraté', 'type' => 'depense', 'color' => '#ffffff', 'icon' => 'tabler:skull']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('Loyer', $this->reload($id)->getName());
    }

    /**
     * Deux catégories actives de même nom et de même type seraient indiscernables à la saisie.
     */
    public function testDuplicateNameIsRejectedOnCreation(): void
    {
        $this->createCategory(name: 'Courses');

        $this->submit('/category/create', 'category', self::VALID);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(1, $this->countCategories());
    }

    public function testRenamingToAnExistingNameIsRejected(): void
    {
        $this->createCategory(name: 'Courses');
        $id = $this->createCategory(name: 'Loyer');

        $this->submit('/category/edit/'.$id, 'category_edit', ['name' => 'Courses', 'type' => 'depense', 'color' => '#000000', 'icon' => 'tabler:home']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('Loyer', $this->reload($id)->getName());
    }

    /**
     * Garde-fou : la règle d'unicité ne doit pas empêcher d'enregistrer une catégorie sous son propre nom.
     */
    public function testKeepingItsOwnNameIsNotADuplicate(): void
    {
        $id = $this->createCategory(name: 'Loyer');

        $this->submit('/category/edit/'.$id, 'category_edit', ['name' => 'Loyer', 'type' => 'depense', 'color' => '#ffffff', 'icon' => 'tabler:home']);

        self::assertResponseRedirects();
        self::assertSame('#ffffff', $this->reload($id)->getColor());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function sameNameInAnotherCase(): iterable
    {
        yield 'minuscules' => ['Courses', 'courses'];
        yield 'majuscule accentuée' => ['épargne', 'Épargne'];
    }

    /**
     * « loyer » et « Loyer » désignent la même catégorie : la casse ne distingue pas deux noms.
     */
    #[DataProvider('sameNameInAnotherCase')]
    public function testDuplicateNameIgnoresCaseOnCreation(string $existing, string $submitted): void
    {
        $this->createCategory(name: $existing);

        $this->submit('/category/create', 'category', [...self::VALID, 'name' => $submitted]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(1, $this->countCategories());
    }

    public function testRenamingToAnExistingNameInAnotherCaseIsRejected(): void
    {
        $this->createCategory(name: 'Courses');
        $id = $this->createCategory(name: 'Loyer');

        $this->submit('/category/edit/'.$id, 'category_edit', ['name' => 'courses', 'type' => 'depense', 'color' => '#000000', 'icon' => 'tabler:home']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('Loyer', $this->reload($id)->getName());
    }

    /**
     * Garde-fou : corriger la casse de son propre nom n'est pas un doublon.
     */
    public function testChangingTheCaseOfItsOwnNameIsAccepted(): void
    {
        $id = $this->createCategory(name: 'loyer');

        $this->submit('/category/edit/'.$id, 'category_edit', ['name' => 'Loyer', 'type' => 'depense', 'color' => '#000000', 'icon' => 'tabler:home']);

        self::assertResponseRedirects();
        self::assertSame('Loyer', $this->reload($id)->getName());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function sameNameWithoutConflict(): iterable
    {
        yield 'autre type' => ['other-type'];
        yield 'homonyme archivée' => ['archived'];
        yield "catégorie d'un autre compte" => ['other-account'];
    }

    /**
     * L'unicité ne vaut qu'entre catégories actives, de même type, du même compte.
     */
    #[DataProvider('sameNameWithoutConflict')]
    public function testSameNameIsAcceptedWhenThereIsNoConflict(string $scenario): void
    {
        $fields = self::VALID;

        match ($scenario) {
            'other-type' => [$this->createCategory(name: 'Courses'), $fields['type'] = 'revenu'],
            'archived' => $this->createCategory(used: true, archived: true, name: 'Courses'),
            'other-account' => $this->createCategory(name: 'Courses', owner: $this->createOtherUser()),
            default => throw new \LogicException($scenario),
        };

        $this->submit('/category/create', 'category', $fields);

        self::assertResponseRedirects();
        self::assertSame(2, $this->countCategories());
    }

    /**
     * @return iterable<string, array{string, array<string, string>}>
     */
    public static function editRequests(): iterable
    {
        yield 'formulaire de modification' => ['GET', []];
        yield 'modification' => ['POST', ['name' => 'Ressuscitée', 'color' => '#ffffff', 'icon' => 'tabler:building']];
    }

    /**
     * Une catégorie archivée est en lecture seule : elle ne sert plus qu'à l'historique.
     *
     * @param array<string, string> $fields
     */
    #[DataProvider('editRequests')]
    public function testArchivedCategoryIsReadOnly(string $method, array $fields): void
    {
        $id = $this->createCategory(used: true, archived: true);

        if ('POST' === $method) {
            $this->submit('/category/edit/'.$id, 'category_edit', $fields);
        } else {
            $this->client->request('GET', '/category/edit/'.$id);
        }

        self::assertContains($this->client->getResponse()->getStatusCode(), [403, 404]);

        $category = $this->reload($id);
        self::assertSame('Loyer', $category->getName());
        self::assertSame('#000000', $category->getColor());
        self::assertSame('tabler:home', $category->getIcon());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function savingForms(): iterable
    {
        yield 'création' => ['create'];
        yield 'modification' => ['edit'];
    }

    #[DataProvider('savingForms')]
    public function testSavingGoesBackToTheList(string $action): void
    {
        if ('create' === $action) {
            $this->submit('/category/create', 'category', self::VALID);
        } else {
            $this->submit('/category/edit/'.$this->createCategory(), 'category_edit', ['name' => 'Logement', 'type' => 'depense', 'color' => '#ffffff', 'icon' => 'tabler:building']);
        }

        self::assertResponseRedirects('/category/show-all');
    }

    #[Group('todo')]
    public function testShowPageDisplaysTheCategory(): void
    {
        $id = $this->createCategory(name: 'Loyer');

        $this->client->request('GET', '/category/show/'.$id);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Loyer');
    }

    /**
     * RG-1.1 — la liste ne montre que les catégories du compte connecté.
     */
    #[Group('todo')]
    public function testListHidesOtherAccountsCategories(): void
    {
        $this->createCategory(name: 'Loyer');
        $this->createCategory(name: 'Salaire de Bob', owner: $this->createOtherUser());

        $this->client->request('GET', '/category/show-all');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Loyer');
        self::assertSelectorTextNotContains('body', 'Salaire de Bob');
    }
}
