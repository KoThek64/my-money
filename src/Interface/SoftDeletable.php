<?php

declare(strict_types=1);

namespace App\Interface;

/**
 * Marqueur des entités à suppression douce (colonne `deletedAt').
 * Le SoftDeleteFilter masque automatiquement les lignes avec lesquelles deletedAt IS NOT NULL,
 * sauf sur l'écran Corbeille où le filtre est désactivé (RG-7).
 */
interface SoftDeletable
{
}
