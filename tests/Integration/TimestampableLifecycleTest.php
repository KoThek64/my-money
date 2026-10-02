<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Category;
use App\Entity\Goal;
use App\Entity\Recurrence;
use App\Entity\Transaction;
use App\Entity\User;
use App\Enum\GoalScopeEnum;
use App\Enum\MovementKindEnum;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Les dates sont posées par Doctrine, pas à la main : sans #[HasLifecycleCallbacks]
 * sur l'entité, le trait Timestampable reste muet.
 */
final class TimestampableLifecycleTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager = $entityManager;

        $this->entityManager->getConnection()->executeStatement('DELETE FROM "user"');
    }

    /**
     * @return iterable<string, array{\Closure(User, Category): (Transaction|Recurrence|Goal)}>
     */
    public static function entities(): iterable
    {
        yield 'transaction' => [static fn (User $user, Category $category): Transaction => new Transaction()
            ->setUser($user)
            ->setCategory($category)
            ->setAmount(1000)
            ->setLabel('Supermarché')
            ->setDate(new \DateTimeImmutable()),
        ];

        yield 'récurrence' => [static fn (User $user, Category $category): Recurrence => new Recurrence()
            ->setUser($user)
            ->setCategory($category)
            ->setAmount(1000)
            ->setLabel('Abonnement')
            ->setDayOfMonth(5),
        ];

        yield 'objectif' => [static fn (User $user, Category $category): Goal => new Goal()
            ->setUser($user)
            ->setCategory($category)
            ->setType(GoalScopeEnum::DEPENSE_CATEGORIE)
            ->setAmount(1000),
        ];
    }

    /**
     * @param \Closure(User, Category): (Transaction|Recurrence|Goal) $factory
     */
    #[DataProvider('entities')]
    public function testDoctrineStampsCreationThenUpdate(\Closure $factory): void
    {
        $user = new User();
        $user->setEmail('alice@my-money.test')
            ->setPassword('irrelevant');

        $category = new Category();
        $category->setName('Courses')
            ->setType(MovementKindEnum::DEPENSE)
            ->setColor('#000000')
            ->setIcon('tabler:cart')
            ->setUser($user);

        $entity = $factory($user, $category);

        $this->entityManager->persist($user);
        $this->entityManager->persist($category);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        self::assertNotNull($entity->getCreatedAt());
        self::assertNull($entity->getUpdatedAt(), 'Une entité jamais modifiée ne porte pas de date de modification.');

        $entity->setAmount(2000);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $reloaded = $this->entityManager->find($entity::class, $entity->getId());
        self::assertNotNull($reloaded);
        self::assertNotNull($reloaded->getUpdatedAt());
    }
}
