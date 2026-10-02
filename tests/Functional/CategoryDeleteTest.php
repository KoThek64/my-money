<?php

declare(strict_types=1);

namespace App\Tests\Functional;

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
 * La suppression modifie des données : POST uniquement, avec jeton CSRF.
 */
final class CategoryDeleteTest extends WebTestCase
{
    private KernelBrowser $client;

    private Uuid $categoryId;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->entityManager()->getConnection()->executeStatement('DELETE FROM "user"');

        $user = new User();
        $user->setEmail('alice@my-money.test')
            ->setPassword('irrelevant');

        $category = new Category();
        $category->setName('Courses')
            ->setType(MovementKindEnum::DEPENSE)
            ->setColor('#000000')
            ->setIcon('tabler:dots')
            ->setUser($user);

        $this->entityManager()->persist($user);
        $this->entityManager()->persist($category);
        $this->entityManager()->flush();
        $this->entityManager()->clear();

        $id = $category->getId();
        self::assertInstanceOf(Uuid::class, $id);
        $this->categoryId = $id;

        $this->client->loginUser($user);
    }

    private function entityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        return $entityManager;
    }

    /**
     * @param array<string, string> $parameters
     * @param array<string, string> $server
     */
    private function delete(string $method, array $parameters = [], array $server = ['HTTP_SEC_FETCH_SITE' => 'same-origin']): void
    {
        // Le jeton est « stateless » : Symfony valide l'origine de la requête.
        $this->client->request($method, '/category/delete/'.$this->categoryId, $parameters, [], $server);
    }

    private function findCategory(): ?Category
    {
        $this->entityManager()->clear();

        return $this->entityManager()->find(Category::class, $this->categoryId);
    }

    private function categoryStillExists(): bool
    {
        return $this->findCategory() instanceof Category;
    }

    /**
     * Une transaction suffit à rendre la catégorie « utilisée » (RG-4).
     */
    private function useCategory(): void
    {
        $category = $this->findCategory();
        self::assertInstanceOf(Category::class, $category);

        $this->entityManager()->persist(new Transaction()
            ->setUser($category->getUser())
            ->setCategory($category)
            ->setAmount(1000)
            ->setLabel('Supermarché')
            ->setDate(new \DateTimeImmutable()));
        $this->entityManager()->flush();
        $this->entityManager()->clear();
    }

    private function archiveCategory(): \DateTimeImmutable
    {
        $archivedAt = new \DateTimeImmutable('2026-01-15 10:00:00');

        $category = $this->findCategory();
        self::assertInstanceOf(Category::class, $category);
        $category->setArchivedAt($archivedAt);
        $this->entityManager()->flush();
        $this->entityManager()->clear();

        return $archivedAt;
    }

    public function testGetIsRefused(): void
    {
        $this->delete('GET');

        self::assertResponseStatusCodeSame(405);
        self::assertTrue($this->categoryStillExists());
    }

    public function testPostWithoutCsrfTokenIsRefused(): void
    {
        $this->delete('POST');

        // InvalidCsrfTokenException est une AuthenticationException : le firewall renvoie au login.
        self::assertResponseRedirects('/login');
        self::assertTrue($this->categoryStillExists());
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function crossOriginRequests(): iterable
    {
        yield 'Sec-Fetch-Site: cross-site' => [['HTTP_SEC_FETCH_SITE' => 'cross-site']];
        yield 'Origin étrangère' => [['HTTP_ORIGIN' => 'https://evil.example']];
    }

    /**
     * Le jeton seul ne prouve rien (sa valeur est publique) : c'est l'origine qui fait foi.
     *
     * @param array<string, string> $server
     */
    #[DataProvider('crossOriginRequests')]
    public function testCrossOriginPostIsRefused(array $server): void
    {
        $this->delete('POST', ['_token' => 'csrf-token'], $server);

        self::assertResponseRedirects('/login');
        self::assertTrue($this->categoryStillExists());
    }

    public function testUnusedCategoryIsReallyDeleted(): void
    {
        $this->delete('POST', ['_token' => 'csrf-token']);

        self::assertResponseRedirects();
        self::assertFalse($this->categoryStillExists());
    }

    /**
     * RG-4 — par l'URL, de bout en bout : une catégorie utilisée est archivée, jamais effacée.
     */
    public function testUsedCategoryIsArchived(): void
    {
        $this->useCategory();

        $this->delete('POST', ['_token' => 'csrf-token']);

        self::assertResponseRedirects();
        self::assertNotNull($this->findCategory()?->getArchivedAt());
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function archivedCategories(): iterable
    {
        yield 'encore utilisée' => [true];
        // Possible après la purge de la corbeille (RG-7) : plus rien ne la référence.
        yield 'plus utilisée' => [false];
    }

    /**
     * Une catégorie archivée est en lecture seule : on ne la supprime pas une seconde fois.
     */
    #[Group('todo')]
    #[DataProvider('archivedCategories')]
    public function testArchivedCategoryCannotBeDeletedAgain(bool $used): void
    {
        if ($used) {
            $this->useCategory();
        }
        $archivedAt = $this->archiveCategory();

        $this->delete('POST', ['_token' => 'csrf-token']);

        self::assertContains($this->client->getResponse()->getStatusCode(), [403, 404]);
        self::assertEquals($archivedAt, $this->findCategory()?->getArchivedAt());
    }

    #[Group('todo')]
    public function testDeletionGoesBackToTheList(): void
    {
        $this->delete('POST', ['_token' => 'csrf-token']);

        self::assertResponseRedirects('/category/show-all');
    }
}
