<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Category;
use App\Entity\Goal;
use App\Entity\Recurrence;
use App\Entity\Transaction;
use App\Enum\MovementKindEnum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Category>
 */
class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    public function isUsed(Category $category): bool
    {
        $filters = $this->getEntityManager()->getFilters();
        $softDeleteWasEnabled = $filters->isEnabled('soft_delete');
        if ($softDeleteWasEnabled) {
            $filters->disable('soft_delete');
        }

        try {
            $qb = $this->createQueryBuilder('c');
            $count = $qb
                ->select('COUNT(c.id)')
                ->where('c = :category')
                ->andWhere($qb->expr()->orX(
                    $qb->expr()->exists($this->referencingRows(Transaction::class, 't')),
                    $qb->expr()->exists($this->referencingRows(Recurrence::class, 'r')),
                    $qb->expr()->exists($this->referencingRows(Goal::class, 'g')),
                ))
                ->setParameter('category', $category)
                ->getQuery()
                ->getSingleScalarResult();
        } finally {
            if ($softDeleteWasEnabled) {
                $filters->enable('soft_delete');
            }
        }

        return 0 < $count;
    }

    /**
     * @return list<Category>
     */
    public function findActive(?MovementKindEnum $type = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.archivedAt IS NULL');

        if (null !== $type) {
            $qb->andWhere('c.type = :type')
                ->setParameter('type', $type);
        }

        $qb->orderBy('c.type', 'ASC')
            ->addOrderBy('c.name', 'ASC');

        return $qb->getQuery()->getResult();
    }

    /**
     * @param array<string, mixed> $criteria
     *
     * @return list<Category>
     */
    public function findActiveDuplicates(array $criteria): array
    {
        return $this->createQueryBuilder('c')
            ->where('LOWER(c.name) = LOWER(:name)')
            ->andWhere('c.type = :type')
            ->andWhere('c.archivedAt IS NULL')
            ->setParameter('name', $criteria['name'])
            ->setParameter('type', $criteria['type'])
            ->getQuery()
            ->getResult();
    }

    /**
     * @param class-string $entity
     */
    private function referencingRows(string $entity, string $alias): string
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select($alias.'.id')
            ->from($entity, $alias)
            ->where($alias.'.category = c')
            ->getDQL();
    }
}
