<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Création d'un compte : hachage du mot de passe et jeu de catégories de base,
 * en une seule transaction (F10, RG-4).
 */
final readonly class UserRegistrerService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private DefaultCategoryInstaller $defaultCategoryInstaller,
    ) {
    }

    public function register(User $user, string $plainPassword): void
    {
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));

        $this->entityManager->persist($user);
        $this->defaultCategoryInstaller->installFor($user);

        // Un seul flush : jamais de compte sans ses catégories, ni l'inverse.
        $this->entityManager->flush();
    }
}
