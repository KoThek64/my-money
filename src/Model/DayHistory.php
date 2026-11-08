<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\Transaction;

final readonly class DayHistory
{
    /**
     * @param list<Transaction> $transactions
     */
    public function __construct(
        public \DateTimeImmutable $date,
        public array $transactions,
        public int $total,
    ) {
    }
}
