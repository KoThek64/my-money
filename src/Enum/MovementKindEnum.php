<?php

declare(strict_types=1);

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum MovementKindEnum: string implements TranslatableInterface
{
    case DEPENSE = 'depense';
    case REVENU = 'revenu';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('enum.movement_kind.'.$this->value, locale: $locale);
    }
}
