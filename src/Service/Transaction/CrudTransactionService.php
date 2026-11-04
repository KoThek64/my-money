<?php

declare(strict_types=1);

namespace App\Service\Transaction;

use App\Entity\Transaction;
use Doctrine\ORM\EntityManagerInterface;

readonly class CrudTransactionService
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function prepare(): Transaction
    {
        $transaction = new Transaction();
        $transaction->setDate(new \DateTimeImmutable());

        return $transaction;
    }

    public function create(Transaction $transaction): void
    {
        $this->em->persist($transaction);
        $this->em->flush();
    }

    public function edit(): void
    {
        $this->em->flush();
    }

    public function delete(Transaction $transaction): void
    {
        $transaction->setDeletedAt(new \DateTimeImmutable());
        $this->em->flush();
    }
}
