<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\Category;
use App\Entity\Transaction;
use App\Entity\User;
use App\Security\Voter\ArchivedCategoryVoter;
use App\Security\Voter\OwnershipVoter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * Une catégorie archivée est en lecture seule : elle ne sert plus qu'à l'historique.
 */
final class ArchivedCategoryVoterTest extends TestCase
{
    private ArchivedCategoryVoter $voter;

    protected function setUp(): void
    {
        $this->voter = new ArchivedCategoryVoter();
    }

    private function token(): TokenInterface
    {
        $user = new User()->setEmail('testeur@my-money.test')->setPassword('irrelevant');

        return new UsernamePasswordToken($user, 'main', $user->getRoles());
    }

    private function category(bool $archived): Category
    {
        return new Category()->setArchivedAt($archived ? new \DateTimeImmutable('2026-01-01') : null);
    }

    public function testActiveCategoryCanBeModified(): void
    {
        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($this->token(), $this->category(archived: false), [ArchivedCategoryVoter::MODIFY]),
        );
    }

    /**
     * Le profiler affiche la raison d'un refus : sans elle, un 403 est indébuggable.
     */
    public function testArchivedCategoryCannotBeModified(): void
    {
        $vote = new Vote();

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($this->token(), $this->category(archived: true), [ArchivedCategoryVoter::MODIFY], $vote),
        );
        self::assertNotEmpty($vote->reasons);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function ownershipAttributes(): iterable
    {
        yield 'VIEW' => [OwnershipVoter::VIEW];
        yield 'EDIT' => [OwnershipVoter::EDIT];
        yield 'DELETE' => [OwnershipVoter::DELETE];
    }

    /**
     * Stratégie affirmative : s'il votait sur EDIT, le « oui » d'OwnershipVoter suffirait à passer.
     */
    #[DataProvider('ownershipAttributes')]
    public function testVoterAbstainsOnOwnershipAttributes(string $attribute): void
    {
        self::assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $this->voter->vote($this->token(), $this->category(archived: true), [$attribute]),
        );
    }

    public function testVoterAbstainsOnAnythingButACategory(): void
    {
        self::assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $this->voter->vote($this->token(), new Transaction(), [ArchivedCategoryVoter::MODIFY]),
        );
    }
}
