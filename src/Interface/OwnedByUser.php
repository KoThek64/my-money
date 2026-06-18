<?php

declare(strict_types=1);

namespace App\Interface;

use App\Entity\User;

/**
 * Implémentée par toute entité rattachée à un utilisateur.
 * Point d'accroche de l'isolation des comptes (SQLFilter soft-delete, voters, etc.).
 */
interface OwnedByUser
{
    public function getUser(): ?User;
}
