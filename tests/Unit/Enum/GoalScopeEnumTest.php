<?php

namespace App\Tests\Unit\Enum;

use App\Enum\GoalScopeEnum;
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
