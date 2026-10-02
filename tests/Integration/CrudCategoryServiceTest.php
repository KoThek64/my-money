<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Category;
use App\Entity\Goal;
use App\Entity\Recurrence;
use App\Entity\Transaction;
use App\Entity\User;
use App\Enum\GoalScopeEnum;
use App\Enum\MovementKindEnum;
use App\Service\Category\CrudCategoryService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * RG-4 — une catégorie référencée ailleurs est archivée, jamais supprimée :
 * les FK sans cascade bloqueraient la suppression, ou l'objectif perdrait sa catégorie.
 */
final class CrudCategoryServiceTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager = $entityManager;

        $this->entityManager->getConnection()->executeStatement('DELETE FROM "user"');
    }

    /**
     * @return iterable<string, array{\Closure(User, Category): object}>
     */
    public static function usages(): iterable
    {
        yield 'transaction' => [static fn (User $user, Category $category): object => new Transaction()
            ->setUser($user)
            ->setCategory($category)
            ->setAmount(1000)
            ->setLabel('Supermarché')
            ->setDate(new \DateTimeImmutable()),
        ];

        yield 'transaction en corbeille' => [static fn (User $user, Category $category): object => new Transaction()
            ->setUser($user)
            ->setCategory($category)
            ->setAmount(1000)
            ->setLabel('Supermarché')
            ->setDate(new \DateTimeImmutable())
            ->setDeletedAt(new \DateTimeImmutable()),
        ];

        yield 'récurrence' => [static fn (User $user, Category $category): object => new Recurrence()
            ->setUser($user)
            ->setCategory($category)
            ->setAmount(1000)
            ->setLabel('Abonnement')
            ->setDayOfMonth(5),
        ];

        yield 'objectif' => [static fn (User $user, Category $category): object => new Goal()
            ->setUser($user)
            ->setCategory($category)
            ->setType(GoalScopeEnum::DEPENSE_CATEGORIE)
            ->setAmount(1000),
        ];
    }

    /**
     * @param \Closure(User, Category): object $usage
     */
    #[DataProvider('usages')]
    public function testUsedCategoryIsArchivedInsteadOfDeleted(\Closure $usage): void
    {
        $user = new User();
        $user->setEmail('alice@my-money.test')
            ->setPassword('irrelevant');

        $category = new Category();
        $category->setName('Courses')
            ->setType(MovementKindEnum::DEPENSE)
            ->setColor('#000000')
            ->setIcon('tabler:dots')
            ->setUser($user);

        $this->entityManager->persist($user);
        $this->entityManager->persist($category);
        $this->entityManager->persist($usage($user, $category));
        $this->entityManager->flush();

        /** @var CrudCategoryService $service */
        $service = self::getContainer()->get(CrudCategoryService::class);
        $service->delete($category);

        $this->entityManager->clear();
        $reloaded = $this->entityManager->find(Category::class, $category->getId());

        self::assertNotNull($reloaded);
        self::assertNotNull($reloaded->getArchivedAt());
    }
}
