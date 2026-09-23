<?php

declare(strict_types=1);

namespace App\Service\Category;

use App\Entity\Category;
use App\Entity\User;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;

readonly class CrudCategoryService
{
    public function __construct(
        private EntityManagerInterface $em,
        private CategoryRepository $repository,
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
        // Utilisée : les FK sans cascade bloqueraient la suppression.
        if ($this->repository->isUsed($category)) {
            $category->setArchivedAt(new \DateTimeImmutable());
        } else {
            $this->em->remove($category);
        }
        $this->em->flush();
    }
}
