<?php

namespace App\Trait;

use Doctrine\ORM\Mapping as ORM;

/**
 * Horodatage partagé : createdAt (posé à la création) + updatedAt (à chaque modification).
 * À utiliser sur Transaction / Recurrence / Goal.
 *
 * L'entité qui l'utilise DOIT être annotée #[ORM\HasLifecycleCallbacks]
 * pour que les callbacks PrePersist / PreUpdate soient déclenchés.
 */
trait Timestampable
{
    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\PrePersist]
    public function initTimestamps(): void
    {
        $this->createdAt ??= new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function refreshUpdatedAt(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
