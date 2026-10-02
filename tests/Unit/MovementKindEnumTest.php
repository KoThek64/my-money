<?php

namespace App\Tests\Unit;

use App\Enum\MovementKindEnum;
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
