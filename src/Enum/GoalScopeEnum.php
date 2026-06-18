<?php

declare(strict_types=1);

namespace App\Enum;

enum GoalScopeEnum: string
{
    case DEPENSE_GLOBALE = 'depense_globale';
    case DEPENSE_CATEGORIE = 'depense_categorie';
    case EPARGNE = 'epargne';

    public function label(): string
    {
        return match ($this) {
            self::DEPENSE_GLOBALE => 'Dépense globale',
            self::DEPENSE_CATEGORIE => 'Dépense catégorie',
            self::EPARGNE => 'Épargne',
        };
    }
}
