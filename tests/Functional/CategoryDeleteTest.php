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
     */
    private function delete(string $method, array $parameters = []): void
    {
        // Le jeton est « stateless » : Symfony valide l'origine de la requête.
        $this->client->request($method, '/category/delete/'.$this->categoryId, $parameters, [], [
            'HTTP_SEC_FETCH_SITE' => 'same-origin',
        ]);
    }

    private function categoryStillExists(): bool
    {
        $this->entityManager()->clear();

        return null !== $this->entityManager()->find(Category::class, $this->categoryId);
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

    public function testUnusedCategoryIsReallyDeleted(): void
    {
        $this->delete('POST', ['_token' => 'csrf-token']);

        self::assertResponseRedirects('/');
        self::assertFalse($this->categoryStillExists());
    }
}
