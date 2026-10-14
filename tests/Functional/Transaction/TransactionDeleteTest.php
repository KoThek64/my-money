<?php

declare(strict_types=1);

namespace App\Tests\Functional\Transaction;

use App\Entity\Category;
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
 * RG-7 — supprimer une transaction la met en corbeille : POST uniquement, avec jeton CSRF.
 */
#[Group('todo')]
final class TransactionDeleteTest extends WebTestCase
{
    private KernelBrowser $client;

    private Uuid $transactionId;

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
            ->setIcon('tabler:cart')
            ->setUser($user);

        $transaction = new Transaction()
            ->setUser($user)
            ->setCategory($category)
            ->setAmount(1250)
            ->setLabel('Carrefour')
            ->setDate(new \DateTimeImmutable('2026-03-10'));

        $this->entityManager()->persist($user);
        $this->entityManager()->persist($category);
        $this->entityManager()->persist($transaction);
        $this->entityManager()->flush();
        $this->entityManager()->clear();

        $id = $transaction->getId();
        self::assertInstanceOf(Uuid::class, $id);
        $this->transactionId = $id;

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
        $this->client->request($method, '/transaction/delete/'.$this->transactionId, $parameters, [], $server);
    }

    /**
     * Lu en SQL : la corbeille masquerait la ligne à Doctrine.
     *
     * @return array<string, mixed>|false
     */
    private function row(): array|false
    {
        return $this->entityManager()->getConnection()->fetchAssociative(
            'SELECT deleted_at FROM transaction WHERE id = ?',
            [(string) $this->transactionId],
        );
    }

    private function deletedAt(): mixed
    {
        $row = $this->row();
        self::assertIsArray($row, 'La transaction a été effacée au lieu d\'être mise en corbeille.');

        return $row['deleted_at'];
    }

    public function testDeletionMovesTheTransactionToTheTrash(): void
    {
        $this->delete('POST', ['_token' => 'csrf-token']);

        self::assertResponseRedirects('/');
        self::assertNotNull($this->deletedAt());
    }

    public function testGetIsRefused(): void
    {
        $this->delete('GET');

        self::assertResponseStatusCodeSame(405);
        self::assertNull($this->deletedAt());
    }

    public function testPostWithoutCsrfTokenIsRefused(): void
    {
        $this->delete('POST');

        // InvalidCsrfTokenException est une AuthenticationException : le firewall renvoie au login.
        self::assertResponseRedirects('/login');
        self::assertNull($this->deletedAt());
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
        self::assertNull($this->deletedAt());
    }

    /**
     * Une transaction déjà en corbeille garde sa date de suppression : c'est elle qui compte pour la purge à 30 jours.
     */
    public function testTrashedTransactionCannotBeDeletedAgain(): void
    {
        /** @var RouterInterface $router */
        $router = self::getContainer()->get(RouterInterface::class);
        $router->match('/transaction/delete/'.$this->transactionId);

        $this->entityManager()->getConnection()->executeStatement(
            "UPDATE transaction SET deleted_at = '2026-03-11 08:00:00' WHERE id = ?",
            [(string) $this->transactionId],
        );

        $this->delete('POST', ['_token' => 'csrf-token']);

        self::assertResponseStatusCodeSame(404);
        self::assertSame('2026-03-11 08:00:00', $this->deletedAt());
    }
}
