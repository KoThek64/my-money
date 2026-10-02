<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Category;
use App\Entity\User;
use App\Enum\MovementKindEnum;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * RG-1.2 — forcer l'uuid d'un autre compte dans l'URL ne donne jamais accès à sa catégorie.
 */
final class CategoryAccessTest extends WebTestCase
{
    private KernelBrowser $client;

    private Uuid $bobCategoryId;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->entityManager()->getConnection()->executeStatement('DELETE FROM "user"');

        $alice = new User();
        $alice->setEmail('alice@my-money.test')
            ->setPassword('irrelevant');

        $bob = new User();
        $bob->setEmail('bob@my-money.test')
            ->setPassword('irrelevant');

        $category = new Category();
        $category->setName('Loyer')
            ->setType(MovementKindEnum::DEPENSE)
            ->setColor('#000000')
            ->setIcon('tabler:home')
            ->setUser($bob);

        $this->entityManager()->persist($alice);
        $this->entityManager()->persist($bob);
        $this->entityManager()->persist($category);
        $this->entityManager()->flush();
        $this->entityManager()->clear();

        $id = $category->getId();
        self::assertInstanceOf(Uuid::class, $id);
        $this->bobCategoryId = $id;

        $this->client->loginUser($alice);
    }

    private function entityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        return $entityManager;
    }

    /**
     * @return iterable<string, array{string, string, array<string, mixed>}>
     */
    public static function requests(): iterable
    {
        yield 'consultation' => ['GET', '/category/show/%s', []];
        yield 'formulaire de modification' => ['GET', '/category/edit/%s', []];
        yield 'modification' => ['POST', '/category/edit/%s', ['category_edit' => [
            'name' => 'Piraté',
            'color' => '#ffffff',
            'icon' => 'tabler:skull',
            '_token' => 'csrf-token',
        ]]];
        yield 'suppression' => ['POST', '/category/delete/%s', ['_token' => 'csrf-token']];
    }

    /**
     * @param array<string, mixed> $parameters
     */
    #[DataProvider('requests')]
    public function testAnotherAccountsCategoryIsUnreachable(string $method, string $uri, array $parameters): void
    {
        $this->client->request($method, \sprintf($uri, $this->bobCategoryId), $parameters, [], [
            'HTTP_SEC_FETCH_SITE' => 'same-origin',
        ]);

        self::assertContains($this->client->getResponse()->getStatusCode(), [403, 404]);

        // Le filtre reste armé au nom d'Alice après la requête : il masquerait la ligne de Bob.
        $filters = $this->entityManager()->getFilters();
        if ($filters->isEnabled('owner')) {
            $filters->disable('owner');
        }

        $this->entityManager()->clear();
        $category = $this->entityManager()->find(Category::class, $this->bobCategoryId);
        self::assertInstanceOf(Category::class, $category);
        self::assertSame('Loyer', $category->getName());
        self::assertNull($category->getArchivedAt());
    }

    /**
     * Défense en profondeur : le filtre SQL ne voit pas une entité déjà en mémoire,
     * c'est alors le voter qui doit refuser.
     *
     * @param array<string, mixed> $parameters
     */
    #[DataProvider('requests')]
    public function testVoterRefusesWhenTheFilterCannotHideTheRow(string $method, string $uri, array $parameters): void
    {
        $this->client->disableReboot();
        $this->entityManager()->find(Category::class, $this->bobCategoryId);

        $this->client->request($method, \sprintf($uri, $this->bobCategoryId), $parameters, [], [
            'HTTP_SEC_FETCH_SITE' => 'same-origin',
        ]);

        self::assertResponseStatusCodeSame(403);
    }
}
