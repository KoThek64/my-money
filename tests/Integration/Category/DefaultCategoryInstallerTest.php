<?php

declare(strict_types=1);

namespace App\Tests\Integration\Category;

use App\Entity\Category;
use App\Entity\User;
use App\Enum\MovementKindEnum;
use App\Service\Category\DefaultCategoryInstaller;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * RG-4 — jeu de catégories de base, copié à l'inscription.
 */
final class DefaultCategoryInstallerTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private DefaultCategoryInstaller $installer;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = self::getContainer();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $this->entityManager = $entityManager;

        /** @var DefaultCategoryInstaller $installer */
        $installer = $container->get(DefaultCategoryInstaller::class);
        $this->installer = $installer;

        $this->entityManager->getConnection()->executeStatement('DELETE FROM "user"');
    }

    private function createUser(string $email): User
    {
        $user = new User();
        $user->setEmail($email)
            ->setPassword('irrelevant');

        $this->entityManager->persist($user);
        $this->installer->installFor($user);
        $this->entityManager->flush();

        return $user;
    }

    /**
     * @return list<Category>
     */
    private function categoriesOf(User $user): array
    {
        return $this->entityManager->getRepository(Category::class)
            ->findBy(['user' => $user], ['name' => 'ASC']);
    }

    public function testEveryCategoryIsCreatedAndOwnedByTheUser(): void
    {
        $user = $this->createUser('nouveau@my-money.test');
        $categories = $this->categoriesOf($user);

        self::assertCount(DefaultCategoryInstaller::count(), $categories);

        foreach ($categories as $category) {
            self::assertSame($user->getId(), $category->getUser()?->getId());
            self::assertNull($category->getArchivedAt(), 'Une catégorie de base ne naît pas archivée.');

            // Une clé absente du catalogue serait stockée telle quelle en base.
            self::assertStringNotContainsString('category.default.', (string) $category->getName());
        }
    }

    /**
     * Sinon l'utilisateur se verrait refuser, en modifiant une catégorie de base, une valeur qu'il n'a pas saisie.
     */
    public function testEveryCategoryPassesValidation(): void
    {
        /** @var ValidatorInterface $validator */
        $validator = self::getContainer()->get(ValidatorInterface::class);

        foreach ($this->categoriesOf($this->createUser('valide@my-money.test')) as $category) {
            $violations = $validator->validate($category);

            self::assertCount(0, $violations, $category->getName().' : '.$violations);
        }
    }

    public function testBothMovementKindsAreCovered(): void
    {
        $categories = $this->categoriesOf($this->createUser('kinds@my-money.test'));

        $kinds = array_map(static fn (Category $category): ?MovementKindEnum => $category->getType(), $categories);

        self::assertContains(MovementKindEnum::DEPENSE, $kinds);
        self::assertContains(MovementKindEnum::REVENU, $kinds);
    }

    public function testNamesAreUniqueWithinTheSet(): void
    {
        $categories = $this->categoriesOf($this->createUser('unique@my-money.test'));

        $names = array_map(static fn (Category $category): ?string => $category->getName(), $categories);

        self::assertSame($names, array_values(array_unique($names)), 'Deux catégories de base portent le même nom.');
    }

    /**
     * RG-1 — le jeu est une copie personnelle : deux comptes ne partagent aucune ligne.
     */
    public function testEachAccountGetsItsOwnCopy(): void
    {
        $first = $this->createUser('premier@my-money.test');
        $second = $this->createUser('second@my-money.test');

        $firstIds = array_map(static fn (Category $category): string => (string) $category->getId(), $this->categoriesOf($first));
        $secondIds = array_map(static fn (Category $category): string => (string) $category->getId(), $this->categoriesOf($second));

        self::assertCount(DefaultCategoryInstaller::count(), $secondIds);
        self::assertSame([], array_intersect($firstIds, $secondIds));
    }
}
