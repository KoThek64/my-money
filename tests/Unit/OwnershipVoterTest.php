<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Category;
use App\Entity\User;
use App\Enum\MovementKindEnum;
use App\Security\Voter\OwnershipVoter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Uid\Uuid;

/**
 * RG-1.2 — le voter ne laisse passer que le propriétaire de l'entité.
 */
final class OwnershipVoterTest extends TestCase
{
    private const string OWNER_ID = '0197c3e0-0000-7000-8000-000000000001';
    private const string INTRUDER_ID = '0197c3e0-0000-7000-8000-000000000002';

    private OwnershipVoter $voter;

    protected function setUp(): void
    {
        $this->voter = new OwnershipVoter();
    }

    /**
     * L'id est posé par Doctrine à la persistance : en test unitaire, seule la
     * réflexion permet de simuler une entité déjà enregistrée.
     */
    private function user(string $id): User
    {
        $user = new User();
        $user->setEmail(\sprintf('%s@my-money.test', $id))
            ->setPassword('irrelevant');

        (new \ReflectionProperty(User::class, 'id'))->setValue($user, Uuid::fromString($id));

        return $user;
    }

    private function categoryOwnedBy(?User $owner): Category
    {
        $category = new Category();
        $category->setName('Courses')
            ->setType(MovementKindEnum::DEPENSE)
            ->setColor('#000000')
            ->setIcon('cart')
            ->setUser($owner);

        return $category;
    }

    private function tokenFor(User $user): TokenInterface
    {
        return new UsernamePasswordToken($user, 'main', $user->getRoles());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function attributeProvider(): iterable
    {
        yield 'VIEW' => [OwnershipVoter::VIEW];
        yield 'EDIT' => [OwnershipVoter::EDIT];
        yield 'DELETE' => [OwnershipVoter::DELETE];
    }

    #[DataProvider('attributeProvider')]
    public function testOwnerIsGranted(string $attribute): void
    {
        $owner = $this->user(self::OWNER_ID);

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($this->tokenFor($owner), $this->categoryOwnedBy($owner), [$attribute]),
        );
    }

    #[DataProvider('attributeProvider')]
    public function testAnotherAccountIsDenied(string $attribute): void
    {
        $owner = $this->user(self::OWNER_ID);
        $intruder = $this->user(self::INTRUDER_ID);

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($this->tokenFor($intruder), $this->categoryOwnedBy($owner), [$attribute]),
            "Forcer l'uuid d'un autre compte doit être refusé (RG-1).",
        );
    }

    /**
     * Le cas qui compte : deux instances PHP distinctes du même compte, comme
     * après un rechargement depuis la session. Une comparaison par === échouerait.
     */
    public function testSameAccountOnTwoDistinctInstancesIsGranted(): void
    {
        $fromSession = $this->user(self::OWNER_ID);
        $fromDatabase = $this->user(self::OWNER_ID);

        self::assertNotSame($fromSession, $fromDatabase);
        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($this->tokenFor($fromSession), $this->categoryOwnedBy($fromDatabase), [OwnershipVoter::VIEW]),
        );
    }

    public function testEntityWithoutOwnerIsDenied(): void
    {
        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($this->tokenFor($this->user(self::OWNER_ID)), $this->categoryOwnedBy(null), [OwnershipVoter::VIEW]),
        );
    }

    public function testUnpersistedOwnerIsDenied(): void
    {
        $owner = $this->user(self::OWNER_ID);
        $category = $this->categoryOwnedBy(new User());

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($this->tokenFor($owner), $category, [OwnershipVoter::VIEW]),
        );
    }

    public function testAnonymousVisitorIsDenied(): void
    {
        $category = $this->categoryOwnedBy($this->user(self::OWNER_ID));

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote(new NullToken(), $category, [OwnershipVoter::VIEW]),
        );
    }

    public function testVoterAbstainsOnUnsupportedAttribute(): void
    {
        $owner = $this->user(self::OWNER_ID);

        self::assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $this->voter->vote($this->tokenFor($owner), $this->categoryOwnedBy($owner), ['PUBLISH']),
        );
    }

    public function testVoterAbstainsOnUnsupportedSubject(): void
    {
        $owner = $this->user(self::OWNER_ID);

        self::assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $this->voter->vote($this->tokenFor($owner), new \stdClass(), [OwnershipVoter::VIEW]),
        );
    }
}
