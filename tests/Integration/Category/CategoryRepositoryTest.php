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

    private function persistedCategory(): Category
    {
        $user = new User();
        $user->setEmail('alice@my-money.test')
            ->setPassword('irrelevant');

        $category = new Category();
        $category->setName('Courses')
            ->setType(MovementKindEnum::DEPENSE)
            ->setColor('#000000')
            ->setIcon('tabler:cart')
            ->setUser($user);

        $this->entityManager->persist($user);
        $this->entityManager->persist($category);
        $this->entityManager->flush();

        return $category;
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
