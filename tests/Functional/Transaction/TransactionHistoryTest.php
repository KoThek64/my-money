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
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Uid\Uuid;

/**
 * F09 — historique mensuel : navigation, filtre, retour après saisie et affichage.
 */
#[Group('todo')]
final class TransactionHistoryTest extends WebTestCase
{
    use ClockSensitiveTrait;

    private KernelBrowser $client;

    private User $user;

    private Uuid $groceries;

    private Uuid $salary;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        self::mockTime('2026-09-15 12:00:00');

        $this->entityManager()->getConnection()->executeStatement('DELETE FROM "user"');

        $this->user = new User();
        $this->user->setEmail('alice@my-money.test')
            ->setPassword('irrelevant');
        $this->entityManager()->persist($this->user);
        $this->entityManager()->flush();

        $this->groceries = $this->createCategory('Courses', MovementKindEnum::DEPENSE);
        $this->salary = $this->createCategory('Salaire', MovementKindEnum::REVENU);

        $this->client->loginUser($this->user);
    }

    private function entityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        return $entityManager;
    }

    private function createCategory(string $name, MovementKindEnum $type): Uuid
    {
        $category = new Category();
        $category->setName($name)
            ->setType($type)
            ->setColor('#000000')
            ->setIcon('tabler:tag')
            ->setUser($this->entityManager()->getReference(User::class, $this->user->getId()));

        $this->entityManager()->persist($category);
        $this->entityManager()->flush();
        $this->entityManager()->clear();

        $id = $category->getId();
        self::assertInstanceOf(Uuid::class, $id);

        return $id;
    }

    private function createTransaction(string $date, string $label, int $amount = 1250, ?Uuid $category = null): Uuid
    {
        $transaction = new Transaction()
            ->setUser($this->entityManager()->getReference(User::class, $this->user->getId()))
            ->setCategory($this->entityManager()->getReference(Category::class, $category ?? $this->groceries))
            ->setAmount($amount)
            ->setLabel($label)
            ->setDate(new \DateTimeImmutable($date));

        $this->entityManager()->persist($transaction);
        $this->entityManager()->flush();
        $this->entityManager()->clear();

        $id = $transaction->getId();
        self::assertInstanceOf(Uuid::class, $id);

        return $id;
    }

    /**
     * @param array<string, string> $fields
     */
    private function submit(string $uri, array $fields): void
    {
        // Jeton « stateless » : Symfony valide l'origine de la requête.
        $this->client->request('POST', $uri, [
            'transaction' => [...$fields, '_token' => 'csrf-token'],
        ], [], ['HTTP_SEC_FETCH_SITE' => 'same-origin']);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function todays(): iterable
    {
        yield 'septembre' => ['2026-09-15', '/history/2026/09'];
        yield 'janvier, mois sur deux chiffres' => ['2027-01-05', '/history/2027/01'];
    }

    #[DataProvider('todays')]
    public function testHistoryOpensOnTheCurrentMonth(string $today, string $expected): void
    {
        self::mockTime($today.' 12:00:00');

        $this->client->request('GET', '/history');

        self::assertResponseRedirects($expected);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validMonths(): iterable
    {
        yield 'mois en cours' => ['/history/2026/09'];
        yield 'mois passé' => ['/history/2025/12'];
        yield 'mois futur' => ['/history/2027/03'];
        yield 'filtre dépenses' => ['/history/2026/09?type=depense'];
        yield 'filtre revenus' => ['/history/2026/09?type=revenu'];
    }

    #[DataProvider('validMonths')]
    public function testMonthPageResponds(string $uri): void
    {
        $this->client->request('GET', $uri);

        self::assertResponseIsSuccessful();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidMonths(): iterable
    {
        yield 'mois 13' => ['/history/2026/13'];
        yield 'mois 00' => ['/history/2026/00'];
        yield 'mois sur un chiffre' => ['/history/2026/9'];
        yield 'année non numérique' => ['/history/abcd/09'];
        yield 'filtre inconnu' => ['/history/2026/09?type=autre'];
    }

    #[DataProvider('invalidMonths')]
    public function testInvalidMonthIsNotFound(string $uri): void
    {
        $this->client->request('GET', $uri);

        self::assertResponseStatusCodeSame(404);
    }

    public function testCreationReturnsToTheMonthOfTheTransaction(): void
    {
        $this->submit('/transaction/create', [
            'amount' => '12,99',
            'date' => '2026-03-15',
            'category' => (string) $this->groceries,
            'label' => 'Boulangerie',
        ]);

        self::assertResponseRedirects('/history/2026/03');
    }

    /**
     * Après un changement de date, on retrouve la transaction là où elle est désormais.
     */
    public function testEditReturnsToTheNewMonthOfTheTransaction(): void
    {
        $id = $this->createTransaction('2026-03-10', 'Carrefour');

        $this->submit('/transaction/edit/'.$id, [
            'amount' => '20,00',
            'date' => '2026-04-02',
            'category' => (string) $this->groceries,
            'label' => 'Carrefour',
        ]);

        self::assertResponseRedirects('/history/2026/04');
    }

    public function testDeletionReturnsToTheMonthOfTheTransaction(): void
    {
        $id = $this->createTransaction('2026-03-10', 'Carrefour');

        $this->client->request('POST', '/transaction/delete/'.$id, ['_token' => 'csrf-token'], [], ['HTTP_SEC_FETCH_SITE' => 'same-origin']);

        self::assertResponseRedirects('/history/2026/03');
    }

    #[Group('template')]
    public function testHeaderShowsTheMonthAndTheYear(): void
    {
        $this->client->request('GET', '/history/2026/09');

        self::assertSelectorTextContains('body', 'Septembre 2026');
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function arrows(): iterable
    {
        yield 'milieu d\'année' => ['/history/2026/09', '/history/2026/08', '/history/2026/10'];
        yield 'janvier recule en décembre' => ['/history/2027/01', '/history/2026/12', '/history/2027/02'];
        yield 'décembre avance en janvier' => ['/history/2026/12', '/history/2026/11', '/history/2027/01'];
    }

    #[DataProvider('arrows')]
    #[Group('template')]
    public function testArrowsLeadToThePreviousAndNextMonths(string $uri, string $previous, string $next): void
    {
        $crawler = $this->client->request('GET', $uri);

        self::assertCount(1, $crawler->filter(\sprintf('a[href="%s"]', $previous)), 'Flèche ◀ absente.');
        self::assertCount(1, $crawler->filter(\sprintf('a[href="%s"]', $next)), 'Flèche ▶ absente.');
    }

    #[Group('template')]
    public function testTransactionsAndTotalsAreDisplayed(): void
    {
        $this->createTransaction('2026-09-12', 'Boulangerie', 1250);
        // Montants sans séparateur de milliers : l'espace insécable varie selon la version d'ICU.
        $this->createTransaction('2026-09-01', 'Salaire', 50000, $this->salary);

        $this->client->request('GET', '/history/2026/09');

        self::assertSelectorTextContains('body', 'Boulangerie');
        self::assertSelectorTextContains('body', 'Samedi 12 septembre');
        self::assertSelectorTextContains('body', '12,50');
        self::assertSelectorTextContains('body', '500,00');
    }

    #[Group('template')]
    public function testTypeFilterNarrowsTheList(): void
    {
        $this->createTransaction('2026-09-12', 'Boulangerie');
        $this->createTransaction('2026-09-01', 'Salaire de septembre', 250000, $this->salary);

        $this->client->request('GET', '/history/2026/09?type=depense');

        self::assertSelectorTextContains('body', 'Boulangerie');
        self::assertSelectorTextNotContains('body', 'Salaire de septembre');
    }

    /**
     * RG-3b — une transaction future est marquée « À venir ».
     */
    #[Group('template')]
    public function testFutureTransactionIsMarkedAsUpcoming(): void
    {
        $this->createTransaction('2026-09-02', 'Passée');
        $this->client->request('GET', '/history/2026/09');
        self::assertSelectorTextNotContains('body', 'À venir');

        $this->createTransaction('2026-09-20', 'Assurance auto', 4250);
        $this->client->request('GET', '/history/2026/09');
        self::assertSelectorTextContains('body', 'À venir');
    }

    #[Group('template')]
    public function testEmptyMonthSaysSo(): void
    {
        $this->client->request('GET', '/history/2026/09');

        self::assertSelectorTextContains('body', 'Aucune transaction en septembre 2026');
    }
}
