<?php

declare(strict_types=1);

namespace App\Service\Category;

use App\Entity\Category;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

readonly class CrudCategoryService
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function create(
        User $user,
        Category $category,
    ): void {
        $category->setUser($user);

        $this->em->persist($category);
        $this->em->flush();
    }

    public function edit(): void
    {
        $this->em->flush();
    }

    public function delete(Category $category): void
    {
        if (0 !== $category->getTransactions()->count()) {
            $category->setArchivedAt(new \DateTimeImmutable());
            $this->em->persist($category);
        } else {
            $this->em->remove($category);
        }
        $this->em->flush();
    }
}
