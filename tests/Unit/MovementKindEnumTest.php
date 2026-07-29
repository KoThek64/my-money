<?php

namespace App\Tests\Unit;

use App\Enum\MovementKindEnum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MovementKindEnumTest extends TestCase
{
    public function testCases(): void
    {
        $this->assertSame(
            ['depense', 'revenu'],
            array_column(MovementKindEnum::cases(), 'value'),
        );
    }

    public function testLabels(): void
    {
        $this->assertSame('Dépense', MovementKindEnum::DEPENSE->label());
        $this->assertSame('Revenu', MovementKindEnum::REVENU->label());
    }

    /**
     * Garde-fou : un case ajouté sans branche dans le match() de label() lèverait
     * une \UnhandledMatchError, que ce test attrape avant la mise en production.
     */
    #[DataProvider('caseProvider')]
    public function testEveryCaseHasANonEmptyLabel(MovementKindEnum $case): void
    {
        $this->assertNotSame('', $case->label());
    }

    /**
     * @return iterable<string, array{MovementKindEnum}>
     */
    public static function caseProvider(): iterable
    {
        foreach (MovementKindEnum::cases() as $case) {
            yield $case->value => [$case];
        }
    }

    public function testFromStoredValue(): void
    {
        $this->assertSame(MovementKindEnum::DEPENSE, MovementKindEnum::from('depense'));
        $this->assertSame(MovementKindEnum::REVENU, MovementKindEnum::from('revenu'));
    }

    public function testFromUnknownValueThrows(): void
    {
        $this->expectException(\ValueError::class);

        MovementKindEnum::from('inconnu');
    }

    public function testTryFromUnknownValueReturnsNull(): void
    {
        $this->assertNull(MovementKindEnum::tryFrom('inconnu'));
    }
}
