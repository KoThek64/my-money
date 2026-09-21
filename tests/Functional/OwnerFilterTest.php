<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Category;
use App\Entity\User;
use App\Enum\MovementKindEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * RG-1.1 — les lectures sont filtrées sur l'utilisateur connecté : la donnée
 * d'un autre compte n'arrive jamais jusqu'au code appelant.
 */
final class OwnerFilterTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();

        // Les FK user_id sont en CASCADE : vider user suffit.
        $this->entityManager()->getConnection()->executeStatement('DELETE FROM "user"');
    }

    /**
     * Le kernel est rebooté à chaque requête : on relit le service au lieu de
     * garder celui d'avant la requête.
     */
    private function entityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        return $entityManager;
    }

    private function createUser(string $email): User
    {
        $user = new User();
        $user->setEmail($email)
            ->setPassword('irrelevant');

        $this->entityManager()->persist($user);
        $this->entityManager()->flush();

        return $user;
    }

    private function createCategory(User $user, string $name): Category
    {
        $category = new Category();
        $category->setName($name)
            ->setType(MovementKindEnum::DEPENSE)
            ->setColor('#000000')
            ->setIcon('tabler:dots')
            ->setUser($user);

        $this->entityManager()->persist($category);
        $this->entityManager()->flush();

        return $category;
    }

    /**
     * Le filtre agit en SQL : il ne peut rien contre une entité déjà hydratée.
     * On vide donc l'EntityManager pour retrouver les conditions d'une vraie
     * requête, qui démarre sur un EntityManager vierge.
     */
    private function browse(string $uri, ?User $user = null): void
    {
        $this->entityManager()->clear();

        if ($user instanceof User) {
            $this->client->loginUser($user);
        }

        $this->client->request('GET', $uri);
    }

    public function testReadsOnlyReturnTheLoggedInUsersRows(): void
    {
        $alice = $this->createUser('alice@my-money.test');
        $bob = $this->createUser('bob@my-money.test');
        $this->createCategory($alice, 'Courses');
        $this->createCategory($bob, 'Loyer');

        $this->browse('/', $alice);
        self::assertResponseIsSuccessful();

        $names = array_map(
            static fn (Category $category): ?string => $category->getName(),
            $this->entityManager()->getRepository(Category::class)->findAll(),
        );

        self::assertSame(['Courses'], $names);
    }

    /**
     * Le scénario d'attaque de RG-1.2, une couche plus bas : même en connaissant
     * l'uuid, la ligne ne remonte pas — le contrôleur n'a qu'un 404 à rendre.
     */
    public function testForcingAnotherAccountsUuidFindsNothing(): void
    {
        $alice = $this->createUser('alice@my-money.test');
        $bob = $this->createUser('bob@my-money.test');
        $bobCategoryId = $this->createCategory($bob, 'Loyer')->getId();
        self::assertInstanceOf(Uuid::class, $bobCategoryId);

        $this->browse('/', $alice);

        self::assertNull($this->entityManager()->find(Category::class, $bobCategoryId));
    }

    /**
     * Le filtre est armé même sans session : une route ouverte par erreur ne
     * doit pas déverser les données de tout le monde.
     */
    public function testAnonymousRequestReadsNothing(): void
    {
        $this->createCategory($this->createUser('alice@my-money.test'), 'Courses');

        $this->browse('/login');
        self::assertResponseIsSuccessful();

        self::assertSame([], $this->entityManager()->getRepository(Category::class)->findAll());
    }
}
