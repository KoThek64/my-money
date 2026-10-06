<?php

declare(strict_types=1);

namespace App\Tests\Integration\Category;

use App\Entity\Category;
use App\Entity\User;
use App\Enum\MovementKindEnum;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMInvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * RG-7 — isUsed() coupe le filtre corbeille le temps de sa requête : il doit le
 * rendre comme il l'a trouvé, sinon la corbeille fuirait dans la suite de la requête.
 */
final class CategoryRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    private CategoryRepository $repository;

    private ?User $owner = null;

    protected function setUp(): void
    {
        self::bootKernel();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager = $entityManager;

        /** @var CategoryRepository $repository */
        $repository = self::getContainer()->get(CategoryRepository::class);
        $this->repository = $repository;

        $this->entityManager->getConnection()->executeStatement('DELETE FROM "user"');
    }

    private function persistedCategory(
        string $name = 'Courses',
        MovementKindEnum $type = MovementKindEnum::DEPENSE,
        bool $archived = false,
    ): Category {
        if (!$this->owner instanceof User) {
            $this->owner = new User();
            $this->owner->setEmail('alice@my-money.test')
                ->setPassword('irrelevant');
            $this->entityManager->persist($this->owner);
        }

        $category = new Category();
        $category->setName($name)
            ->setType($type)
            ->setColor('#000000')
            ->setIcon('tabler:cart')
            ->setUser($this->owner);
        if ($archived) {
            $category->setArchivedAt(new \DateTimeImmutable());
        }

        $this->entityManager->persist($category);
        $this->entityManager->flush();

        return $category;
    }

    /**
     * @param list<Category> $categories
     *
     * @return list<string|null>
     */
    private static function names(array $categories): array
    {
        return array_map(static fn (Category $category): ?string => $category->getName(), $categories);
    }

    public function testFindActiveLeavesArchivedCategoriesOut(): void
    {
        $this->persistedCategory('Loyer');
        $this->persistedCategory('Ancienne', archived: true);

        self::assertSame(['Loyer'], self::names($this->repository->findActive()));
    }

    /**
     * @return iterable<string, array{MovementKindEnum, string}>
     */
    public static function types(): iterable
    {
        yield 'dépenses' => [MovementKindEnum::DEPENSE, 'Loyer'];
        yield 'revenus' => [MovementKindEnum::REVENU, 'Salaire'];
    }

    #[DataProvider('types')]
    public function testFindActiveKeepsOnlyTheRequestedType(MovementKindEnum $type, string $expected): void
    {
        $this->persistedCategory('Loyer', MovementKindEnum::DEPENSE);
        $this->persistedCategory('Salaire', MovementKindEnum::REVENU);

        self::assertSame([$expected], self::names($this->repository->findActive($type)));
    }

    public function testFindActiveSortsByTypeThenByName(): void
    {
        $this->persistedCategory('Salaire', MovementKindEnum::REVENU);
        $this->persistedCategory('Loyer', MovementKindEnum::DEPENSE);
        $this->persistedCategory('Bonus', MovementKindEnum::REVENU);
        $this->persistedCategory('Courses', MovementKindEnum::DEPENSE);

        self::assertSame(
            ['Courses', 'Loyer', 'Bonus', 'Salaire'],
            self::names($this->repository->findActive()),
        );
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function filterStates(): iterable
    {
        yield 'filtre actif (cas normal)' => [true];
        yield 'filtre coupé (écran Corbeille)' => [false];
    }

    #[DataProvider('filterStates')]
    public function testIsUsedLeavesTheTrashFilterAsItFoundIt(bool $enabled): void
    {
        $category = $this->persistedCategory();
        $filters = $this->entityManager->getFilters();
        if (!$enabled) {
            $filters->disable('soft_delete');
        }

        $this->repository->isUsed($category);

        self::assertSame($enabled, $filters->isEnabled('soft_delete'));
    }

    public function testIsUsedRestoresTheTrashFilterEvenWhenTheQueryFails(): void
    {
        try {
            // Une catégorie jamais enregistrée n'a pas d'identifiant : la requête échoue.
            $this->repository->isUsed(new Category());
            self::fail('La requête aurait dû échouer.');
        } catch (ORMInvalidArgumentException) {
        }

        self::assertTrue($this->entityManager->getFilters()->isEnabled('soft_delete'));
    }
}
