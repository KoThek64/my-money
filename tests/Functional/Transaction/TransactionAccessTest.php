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
 * RG-1.2 — forcer l'uuid d'un autre compte dans l'URL ne donne jamais accès à sa transaction.
 */
#[Group('todo')]
final class TransactionAccessTest extends WebTestCase
{
    private KernelBrowser $client;

    private Uuid $bobTransactionId;

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
        $category->setName('Courses')
            ->setType(MovementKindEnum::DEPENSE)
            ->setColor('#000000')
            ->setIcon('tabler:cart')
            ->setUser($bob);

        $transaction = new Transaction()
            ->setUser($bob)
            ->setCategory($category)
            ->setAmount(1250)
            ->setLabel('Carrefour')
            ->setDate(new \DateTimeImmutable('2026-03-10'));

        $this->entityManager()->persist($alice);
        $this->entityManager()->persist($bob);
        $this->entityManager()->persist($category);
        $this->entityManager()->persist($transaction);
        $this->entityManager()->flush();
        $this->entityManager()->clear();

        $id = $transaction->getId();
        self::assertInstanceOf(Uuid::class, $id);
        $this->bobTransactionId = $id;

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
        yield 'formulaire de modification' => ['GET', '/transaction/edit/%s', []];
        yield 'modification' => ['POST', '/transaction/edit/%s', ['transaction' => [
            'amount' => '999,00',
            'date' => '2026-03-15',
            'label' => 'Piraté',
            '_token' => 'csrf-token',
        ]]];
        yield 'suppression' => ['POST', '/transaction/delete/%s', ['_token' => 'csrf-token']];
    }

    /**
     * @param array<string, mixed> $parameters
     */
    #[DataProvider('requests')]
    public function testAnotherAccountsTransactionIsUnreachable(string $method, string $uri, array $parameters): void
    {
        $uri = \sprintf($uri, $this->bobTransactionId);

        // Sans la route, le 404 attendu viendrait du routeur et ne prouverait rien.
        /** @var RouterInterface $router */
        $router = self::getContainer()->get(RouterInterface::class);
        $router->match($uri);

        $this->client->request($method, $uri, $parameters, [], ['HTTP_SEC_FETCH_SITE' => 'same-origin']);

        self::assertContains($this->client->getResponse()->getStatusCode(), [403, 404]);

        // En SQL : ni le filtre propriétaire ni la corbeille ne doivent masquer la ligne de Bob.
        $row = $this->entityManager()->getConnection()->fetchAssociative(
            'SELECT amount, label, deleted_at FROM transaction WHERE id = ?',
            [(string) $this->bobTransactionId],
        );
        self::assertIsArray($row);
        self::assertSame(1250, $row['amount']);
        self::assertSame('Carrefour', $row['label']);
        self::assertNull($row['deleted_at']);
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
        $this->entityManager()->find(Transaction::class, $this->bobTransactionId);

        $this->client->request($method, \sprintf($uri, $this->bobTransactionId), $parameters, [], [
            'HTTP_SEC_FETCH_SITE' => 'same-origin',
        ]);

        self::assertResponseStatusCodeSame(403);
    }
}
