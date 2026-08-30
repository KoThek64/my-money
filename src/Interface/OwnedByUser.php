<?php

declare(strict_types=1);

namespace App\Interface;

use App\Entity\User;

/**
 * Entité rattachée à un utilisateur : point d'accroche de l'isolation (RG-1).
 */
interface OwnedByUser
{
    public function getUser(): ?User;

    public function setUser(?User $user): static;
}
