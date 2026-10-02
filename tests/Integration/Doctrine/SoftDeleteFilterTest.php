<?php

declare(strict_types=1);

namespace App\Tests\Integration\Doctrine;

use App\Entity\Category;
use App\Entity\Transaction;
use App\Entity\User;
use App\Enum\MovementKindEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * RG-7 — une transaction en corbeille sort de toutes les lectures, sauf demande explicite.
 */
final class SoftDeleteFilterTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager = $entityManager;

        $this->entityManager->getConnection()->executeStatement('DELETE FROM "user"');

        $user = new User();
        $user->setEmail('alice@my-money.test')
            ->setPassword('irrelevant');

        $category = new Category();
        $category->setName('Courses')
            ->setType(MovementKindEnum::DEPENSE)
            ->setColor('#000000')
            ->setIcon('tabler:cart')
            ->setUser($user);

        $this->entityManager->persist($user);
        $this->entityManager->persist($category);
        $this->entityManager->persist($this->transaction($user, $category, 'Supermarché'));
        $this->entityManager->persist(
            $this->transaction($user, $category, 'Erreur de saisie')->setDeletedAt(new \DateTimeImmutable()),
        );
        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    private function transaction(User $user, Category $category, string $label): Transaction
    {
        return new Transaction()
            ->setUser($user)
            ->setCategory($category)
            ->setAmount(1000)
            ->setLabel($label)
            ->setDate(new \DateTimeImmutable());
    }

    /**
     * @return list<?string>
     */
    private function labels(): array
    {
        $transactions = $this->entityManager->getRepository(Transaction::class)->findBy([], ['label' => 'ASC']);

        return array_map(static fn (Transaction $transaction): ?string => $transaction->getLabel(), $transactions);
    }

    public function testTrashedTransactionIsHiddenByDefault(): void
    {
        self::assertSame(['Supermarché'], $this->labels());
    }

    /**
     * L'écran Corbeille coupe le filtre pour afficher ce qui est récupérable.
     */
    public function testTrashedTransactionReappearsOnceTheFilterIsDisabled(): void
    {
        $this->entityManager->getFilters()->disable('soft_delete');

        self::assertSame(['Erreur de saisie', 'Supermarché'], $this->labels());
    }
}
