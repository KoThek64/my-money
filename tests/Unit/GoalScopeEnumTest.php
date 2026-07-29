<?php

namespace App\Tests\Unit;

use App\Enum\GoalScopeEnum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GoalScopeEnumTest extends TestCase
{
    public function testCases(): void
    {
        $this->assertSame(
            ['depense_globale', 'depense_categorie', 'epargne'],
            array_column(GoalScopeEnum::cases(), 'value'),
        );
    }

    public function testLabels(): void
    {
        $this->assertSame('Dépense globale', GoalScopeEnum::DEPENSE_GLOBALE->label());
        $this->assertSame('Dépense catégorie', GoalScopeEnum::DEPENSE_CATEGORIE->label());
        $this->assertSame('Épargne', GoalScopeEnum::EPARGNE->label());
    }

    /**
     * Garde-fou : un case ajouté sans branche dans le match() de label() lèverait
     * une \UnhandledMatchError, que ce test attrape avant la mise en production.
     */
    #[DataProvider('caseProvider')]
    public function testEveryCaseHasANonEmptyLabel(GoalScopeEnum $case): void
    {
        $this->assertNotSame('', $case->label());
    }

    /**
     * @return iterable<string, array{GoalScopeEnum}>
     */
    public static function caseProvider(): iterable
    {
        foreach (GoalScopeEnum::cases() as $case) {
            yield $case->value => [$case];
        }
    }

    public function testFromStoredValue(): void
    {
        $this->assertSame(GoalScopeEnum::DEPENSE_GLOBALE, GoalScopeEnum::from('depense_globale'));
        $this->assertSame(GoalScopeEnum::DEPENSE_CATEGORIE, GoalScopeEnum::from('depense_categorie'));
        $this->assertSame(GoalScopeEnum::EPARGNE, GoalScopeEnum::from('epargne'));
    }

    public function testFromUnknownValueThrows(): void
    {
        $this->expectException(\ValueError::class);

        GoalScopeEnum::from('inconnu');
    }

    public function testTryFromUnknownValueReturnsNull(): void
    {
        $this->assertNull(GoalScopeEnum::tryFrom('inconnu'));
    }
}
