<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Category;
use App\Entity\User;
use App\Enum\MovementKindEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * RG-4 — copie personnelle du jeu de catégories de base à l'inscription.
 * Ce sont de vraies lignes, que l'utilisateur peut ensuite renommer ou supprimer.
 */
final readonly class DefaultCategoryInstaller
{
    /**
     * Le nom est traduit une fois, à la création : la ligne appartient ensuite
     * à l'utilisateur, qui reste libre de la renommer.
     *
     * @var list<array{name: string, type: MovementKindEnum, color: string, icon: string}>
     */
    private const array CATEGORIES = [
        ['name' => 'category.default.groceries', 'type' => MovementKindEnum::DEPENSE, 'color' => '#e8590c', 'icon' => 'tabler:shopping-cart'],
        ['name' => 'category.default.rent', 'type' => MovementKindEnum::DEPENSE, 'color' => '#1971c2', 'icon' => 'tabler:home'],
        ['name' => 'category.default.fuel', 'type' => MovementKindEnum::DEPENSE, 'color' => '#5f3dc4', 'icon' => 'tabler:gas-station'],
        ['name' => 'category.default.transport', 'type' => MovementKindEnum::DEPENSE, 'color' => '#0c8599', 'icon' => 'tabler:bus'],
        ['name' => 'category.default.restaurant', 'type' => MovementKindEnum::DEPENSE, 'color' => '#c2255c', 'icon' => 'tabler:tools-kitchen-2'],
        ['name' => 'category.default.leisure', 'type' => MovementKindEnum::DEPENSE, 'color' => '#6741d9', 'icon' => 'tabler:device-gamepad-2'],
        ['name' => 'category.default.health', 'type' => MovementKindEnum::DEPENSE, 'color' => '#e03131', 'icon' => 'tabler:heartbeat'],
        ['name' => 'category.default.subscriptions', 'type' => MovementKindEnum::DEPENSE, 'color' => '#f08c00', 'icon' => 'tabler:repeat'],
        ['name' => 'category.default.other_expense', 'type' => MovementKindEnum::DEPENSE, 'color' => '#868e96', 'icon' => 'tabler:dots'],
        ['name' => 'category.default.salary', 'type' => MovementKindEnum::REVENU, 'color' => '#2f9e44', 'icon' => 'tabler:cash'],
        ['name' => 'category.default.bonus', 'type' => MovementKindEnum::REVENU, 'color' => '#66a80f', 'icon' => 'tabler:gift'],
        ['name' => 'category.default.refund', 'type' => MovementKindEnum::REVENU, 'color' => '#087f5b', 'icon' => 'tabler:arrow-back-up'],
        ['name' => 'category.default.other_income', 'type' => MovementKindEnum::REVENU, 'color' => '#868e96', 'icon' => 'tabler:dots'],
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * Persiste sans flusher : l'appelant décide de la transaction, pour qu'un
     * compte ne puisse pas être créé sans ses catégories.
     */
    public function installFor(User $user): void
    {
        foreach (self::CATEGORIES as $definition) {
            $category = new Category();
            $category->setName($this->translator->trans($definition['name']))
                ->setType($definition['type'])
                ->setColor($definition['color'])
                ->setIcon($definition['icon'])
                ->setUser($user);

            $this->entityManager->persist($category);
        }
    }

    public static function count(): int
    {
        return \count(self::CATEGORIES);
    }
}
