<?php

declare(strict_types=1);

namespace App\Doctrine\Filter;

use App\Interface\OwnedByUser;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

/**
 * RG-1.1 — restreint toute lecture d'une entité OwnedByUser à l'utilisateur
 * connecté. Armé par l'OwnerFilterListener sur chaque requête HTTP.
 */
final class OwnerFilter extends SQLFilter
{
    public const string NAME = 'owner';
    public const string PARAMETER = 'user_id';

    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        if (!$targetEntity->reflClass->implementsInterface(OwnedByUser::class)) {
            return '';
        }

        // Filtre armé sans utilisateur (visiteur anonyme) : on ne renvoie rien
        // plutôt que tout — une isolation ne doit jamais échouer en s'ouvrant.
        if (!$this->hasParameter(self::PARAMETER)) {
            return '1 = 0';
        }

        $column = $targetEntity->getSingleAssociationJoinColumnName('user');

        return sprintf('%s.%s = %s', $targetTableAlias, $column, $this->getParameter(self::PARAMETER));
    }
}
