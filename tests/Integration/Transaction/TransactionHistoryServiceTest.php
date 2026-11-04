<?php

declare(strict_types=1);

namespace App\Tests\Integration\Transaction;

use App\Doctrine\Filter\OwnerFilter;
use App\Entity\Category;
use App\Entity\Transaction;
use App\Entity\User;
use App\Enum\MovementKindEnum;
use App\Model\DayHistory;
use App\Model\MonthHistory;
use App\Service\Transaction\TransactionHistoryService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * F09 / RG-3b — historique mensuel : transactions du mois, totaux, regroupement par jour.
 */
#[Group('todo')]
final class TransactionHistoryServiceTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    private EntityManagerInterface $entityManager;

    private TransactionHistoryService $service;

    private User $alice;

    private Category $groceries;

    private Category $salary;

    protected function setUp(): void
    {
        self::bootKernel();
        self::mockTime('2026-09-15 12:00:00');

        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager = $entityManager;

        /** @var TransactionHistoryService $service */
        $service = self::getContainer()->get(TransactionHistoryService::class);
        $this->service = $service;

        $this->entityManager->getConnection()->executeStatement('DELETE FROM "user"');

        $this->alice = $this->user('alice@my-money.test');
        $this->groceries = $this->category('Courses', MovementKindEnum::DEPENSE, $this->alice);
        $this->salary = $this->category('Salaire', MovementKindEnum::REVENU, $this->alice);

        // Comme OwnerFilterListener à chaque requête : seules les données d'Alice sont visibles.
        $this->entityManager->getFilters()
            ->enable(OwnerFilter::NAME)
            ->setParameter(OwnerFilter::PARAMETER, $this->alice->getId(), UuidType::NAME);
    }

    private function user(string $email): User
    {
        $user = new User();
        $user->setEmail($email)->setPassword('irrelevant');
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function category(string $name, MovementKindEnum $type, User $owner): Category
    {
        $category = new Category();
        $category->setName($name)
            ->setType($type)
            ->setColor('#000000')
            ->setIcon('tabler:tag')
            ->setUser($owner);
        $this->entityManager->persist($category);
        $this->entityManager->flush();

        return $category;
    }

    private function transaction(
        string $date,
        string $label,
        int $amount = 1000,
        ?Category $category = null,
        ?string $savedAt = null,
        bool $trashed = false,
    ): void {
        $category ??= $this->groceries;

        $transaction = new Transaction()
            ->setUser($category->getUser())
            ->setCategory($category)
            ->setAmount($amount)
            ->setLabel($label)
            ->setDate(new \DateTimeImmutable($date));
        if ($trashed) {
            $transaction->setDeletedAt(new \DateTimeImmutable('2026-09-14'));
        }
        $this->entityManager->persist($transaction);
        $this->entityManager->flush();

        // createdAt n'a pas de setter et sa précision est la seconde : on le fixe en base.
        if (null !== $savedAt) {
            $this->entityManager->getConnection()->executeStatement(
                'UPDATE transaction SET created_at = ? WHERE id = ?',
                [$savedAt, (string) $transaction->getId()],
            );
        }
    }

    private function month(int $year, int $month, ?MovementKindEnum $type = null): MonthHistory
    {
        // Le service doit relire la base, pas les entités gardées en mémoire.
        $this->entityManager->clear();

        return $this->service->month($year, $month, $type);
    }

    /**
     * @return list<string|null>
     */
    private static function labels(MonthHistory $history): array
    {
        $labels = [];
        foreach ($history->days as $day) {
            foreach ($day->transactions as $transaction) {
                $labels[] = $transaction->getLabel();
            }
        }

        return $labels;
    }

    /**
     * @param array{int, int, int} $expected dépenses, revenus, solde
     */
    private static function assertTotals(array $expected, MonthHistory $history): void
    {
        self::assertSame($expected, [$history->expenses, $history->incomes, $history->balance]);
    }

    public function testMonthRunsFromItsFirstToItsLastDay(): void
    {
        $this->transaction('2026-08-31', 'Fin août');
        $this->transaction('2026-09-01', 'Premier jour');
        $this->transaction('2026-09-30', 'Dernier jour');
        $this->transaction('2026-10-01', 'Début octobre');

        self::assertSame(['Dernier jour', 'Premier jour'], self::labels($this->month(2026, 9)));
    }

    public function testTransactionsAreSortedFromTheMostRecentDate(): void
    {
        $this->transaction('2026-09-03', 'Le 3');
        $this->transaction('2026-09-10', 'Le 10');
        $this->transaction('2026-09-07', 'Le 7');

        self::assertSame(['Le 10', 'Le 7', 'Le 3'], self::labels($this->month(2026, 9)));
    }

    /**
     * Ordre de saisie ≠ ordre d'insertion ≠ ordre des uuid : seul createdAt donne le bon résultat.
     */
    public function testSameDayTransactionsShowTheLastSavedFirst(): void
    {
        $this->transaction('2026-09-05', 'Midi', savedAt: '2026-09-05 12:00:00');
        $this->transaction('2026-09-05', 'Soir', savedAt: '2026-09-05 18:00:00');
        $this->transaction('2026-09-05', 'Matin', savedAt: '2026-09-05 08:00:00');

        self::assertSame(['Soir', 'Midi', 'Matin'], self::labels($this->month(2026, 9)));
    }

    /**
     * RG-1 / RG-7 — ni la corbeille ni les transactions d'un autre compte.
     */
    public function testTrashAndOtherAccountsStayOut(): void
    {
        $bobsGroceries = $this->category('Courses de Bob', MovementKindEnum::DEPENSE, $this->user('bob@my-money.test'));

        $this->transaction('2026-09-05', 'Visible');
        $this->transaction('2026-09-06', 'À la corbeille', trashed: true);
        $this->transaction('2026-09-07', 'Chez Bob', category: $bobsGroceries);

        $history = $this->month(2026, 9);

        self::assertSame(['Visible'], self::labels($history));
        self::assertTotals([1000, 0, -1000], $history);
    }

    /**
     * RG-2 — le sens vient du type de la catégorie : solde = revenus − dépenses.
     */
    public function testTotalsSumExpensesAndIncomes(): void
    {
        $this->transaction('2026-09-02', 'Courses', 1000);
        $this->transaction('2026-09-03', 'Pharmacie', 250);
        $this->transaction('2026-09-01', 'Salaire', 5000, $this->salary);

        self::assertTotals([1250, 5000, 3750], $this->month(2026, 9));
    }

    /**
     * RG-3b — une transaction compte à partir de sa date : aujourd'hui oui, demain non.
     */
    public function testFutureTransactionsAreListedButNotCounted(): void
    {
        $this->transaction('2026-09-15', 'Aujourd\'hui', 1000);
        $this->transaction('2026-09-16', 'Demain', 400);
        $this->transaction('2026-09-20', 'Prime à venir', 3000, $this->salary);

        $history = $this->month(2026, 9);

        self::assertSame(['Prime à venir', 'Demain', 'Aujourd\'hui'], self::labels($history));
        self::assertTotals([1000, 0, -1000], $history);
    }

    /**
     * @return iterable<string, array{MovementKindEnum, list<string>}>
     */
    public static function typeFilters(): iterable
    {
        yield 'dépenses' => [MovementKindEnum::DEPENSE, ['Courses']];
        yield 'revenus' => [MovementKindEnum::REVENU, ['Salaire']];
    }

    /**
     * Les totaux résument toujours le mois entier, quel que soit le filtre.
     *
     * @param list<string> $expected
     */
    #[DataProvider('typeFilters')]
    public function testTypeFilterNarrowsTheListButNotTheTotals(MovementKindEnum $type, array $expected): void
    {
        $this->transaction('2026-09-02', 'Courses', 1000);
        $this->transaction('2026-09-01', 'Salaire', 5000, $this->salary);

        $history = $this->month(2026, 9, $type);

        self::assertSame($expected, self::labels($history));
        self::assertTotals([1000, 5000, 4000], $history);
    }

    public function testTransactionsAreGroupedByDayWithTheirBalance(): void
    {
        $this->transaction('2026-09-02', 'Boulangerie', 500);
        $this->transaction('2026-09-05', 'Courses', 1000);
        $this->transaction('2026-09-05', 'Salaire', 3000, $this->salary);

        $days = $this->month(2026, 9)->days;

        self::assertSame(
            ['2026-09-05', '2026-09-02'],
            array_map(static fn (DayHistory $day): string => $day->date->format('Y-m-d'), $days),
        );
        self::assertSame([2, 1], array_map(static fn (DayHistory $day): int => \count($day->transactions), $days));
        self::assertSame([2000, -500], array_map(static fn (DayHistory $day): int => $day->total, $days));
    }

    /**
     * RG-3b — comme pour le mois, un jour à venir ne compte pas encore.
     */
    public function testFutureDayTotalIsZero(): void
    {
        $this->transaction('2026-09-20', 'Assurance auto', 4250);

        $days = $this->month(2026, 9)->days;

        self::assertCount(1, $days);
        self::assertSame(0, $days[0]->total);
    }

    public function testEmptyMonthHasNoDayAndZeroTotals(): void
    {
        $this->transaction('2026-08-20', 'Le mois d\'avant');

        $history = $this->month(2026, 9);

        self::assertSame([], $history->days);
        self::assertTotals([0, 0, 0], $history);
    }

    /**
     * F09 — un mois futur s'affiche : ses lignes sont là, ses totaux restent à zéro.
     */
    public function testFutureMonthListsItsTransactionsWithZeroTotals(): void
    {
        $this->transaction('2026-10-05', 'Loyer d\'octobre', 80000);
        $this->transaction('2026-10-01', 'Salaire d\'octobre', 250000, $this->salary);

        $history = $this->month(2026, 10);

        self::assertSame(['Loyer d\'octobre', 'Salaire d\'octobre'], self::labels($history));
        self::assertTotals([0, 0, 0], $history);
    }
}
