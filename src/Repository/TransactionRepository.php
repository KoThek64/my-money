<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Transaction;
use App\Enum\MovementKindEnum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Transaction>
 */
class TransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transaction::class);
    }

    /**
     * @return list<Transaction>
     */
    public function findBetween(\DateTimeImmutable $from, \DateTimeImmutable $to, ?MovementKindEnum $type = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->where('t.date BETWEEN :from AND :to')
            ->setParameter('from', $from, Types::DATE_IMMUTABLE)
            ->setParameter('to', $to, Types::DATE_IMMUTABLE)
            ->join('t.category', 'c');

        if ($type instanceof MovementKindEnum) {
            $qb->andWhere('c.type = :type')
                ->setParameter('type', $type);
        }

        $qb->orderBy('t.date', 'DESC')
            ->addOrderBy('t.createdAt', 'DESC');

        return $qb->getQuery()->getResult();
    }
}
