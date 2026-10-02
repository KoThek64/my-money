<?php

declare(strict_types=1);

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum GoalScopeEnum: string implements TranslatableInterface
{
    case DEPENSE_GLOBALE = 'depense_globale';
    case DEPENSE_CATEGORIE = 'depense_categorie';
    case EPARGNE = 'epargne';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('enum.goal_scope.'.$this->value, locale: $locale);
    }
}
