<?php

declare(strict_types=1);

namespace App\Enum;

enum MovementKindEnum: string
{
    case DEPENSE = 'depense';
    case REVENU = 'revenu';

    public function label(): string
    {
        return match ($this) {
            self::DEPENSE => 'Dépense',
            self::REVENU => 'Revenu',
        };
    }
}
