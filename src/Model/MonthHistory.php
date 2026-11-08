<?php

declare(strict_types=1);

namespace App\Model;

final readonly class MonthHistory
{
    /**
     * @param list<DayHistory> $days
     */
    public function __construct(
        public \DateTimeImmutable $start,
        public int $expenses,
        public int $incomes,
        public int $balance,
        public array $days,
    ) {
    }
}
