<?php

namespace App\Doctrine\Filter;

use App\Interface\SoftDeletable;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

/**
 * Masque les entités SoftDeletable dont deletedAt IS NOT NULL (RG-7).
 * Désactivé uniquement sur l'écran Corbeille.
 */
final class SoftDeleteFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        if (!$targetEntity->reflClass->implementsInterface(SoftDeletable::class)) {
            return '';
        }

        $column = $targetEntity->getColumnName('deletedAt');

        return sprintf('%s.%s IS NULL', $targetTableAlias, $column);
    }
}
