<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Category;
use App\Entity\User;
use App\Enum\MovementKindEnum;
use Doctrine\DBAL\Exception\NotNullConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * RG-1.3 — le propriétaire est posé par le listener, jamais par le formulaire.
 */
final class OwnerAssignmentListenerTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = self::getContainer();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $this->entityManager = $entityManager;

        $this->entityManager->getConnection()->executeStatement('DELETE FROM "user"');
    }

    private function createUser(string $email): User
    {
        $user = new User();
        $user->setEmail($email)
            ->setPassword('irrelevant');

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function login(User $user): void
    {
        self::getContainer()->get('security.token_storage')
            ->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
    }

    private function newCategory(): Category
    {
        $category = new Category();

        return $category->setName('Courses')
            ->setType(MovementKindEnum::DEPENSE)
            ->setColor('#000000')
            ->setIcon('cart');
    }

    public function testOwnerIsAssignedFromTheLoggedInUser(): void
    {
        $user = $this->createUser('proprietaire@my-money.test');
        $this->login($user);

        $category = $this->newCategory();
        self::assertNull($category->getUser(), 'Le propriétaire ne doit pas être posé à la construction.');

        $this->entityManager->persist($category);
        $this->entityManager->flush();

        self::assertSame($user->getId(), $category->getUser()?->getId());
    }

    /**
     * Un propriétaire explicite n'est pas écrasé : fixtures et commandes restent
     * libres de créer des données pour un autre compte.
     */
    public function testExplicitOwnerIsNotOverwritten(): void
    {
        $connected = $this->createUser('connecte@my-money.test');
        $other = $this->createUser('autre@my-money.test');
        $this->login($connected);

        $category = $this->newCategory()->setUser($other);
        $this->entityManager->persist($category);
        $this->entityManager->flush();

        self::assertSame($other->getId(), $category->getUser()?->getId());
    }

    public function testPersistWithoutLoggedInUserIsRejectedByTheDatabase(): void
    {
        $this->entityManager->persist($this->newCategory());

        $this->expectException(NotNullConstraintViolationException::class);
        $this->entityManager->flush();
    }
}
