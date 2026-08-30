<?php

declare(strict_types=1);

namespace App\Doctrine\Listener;

use App\Entity\User;
use App\Interface\OwnedByUser;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * RG-1.3 — rattache toute entité OwnedByUser à l'utilisateur connecté, jamais
 * via un champ de formulaire, qui serait modifiable côté client.
 */
#[AsDoctrineListener(event: Events::prePersist)]
final readonly class OwnerAssignmentListener
{
    public function __construct(private Security $security)
    {
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();

        // Un propriétaire déjà posé n'est jamais écrasé : fixtures, commandes
        // et création pour un tiers restent possibles.
        if (!$entity instanceof OwnedByUser || $entity->getUser() instanceof User) {
            return;
        }

        $user = $this->security->getUser();

        // Hors requête authentifiée (CLI, fixtures) : on laisse le NOT NULL parler.
        if (!$user instanceof User) {
            return;
        }

        $entity->setUser($user);
    }
}
