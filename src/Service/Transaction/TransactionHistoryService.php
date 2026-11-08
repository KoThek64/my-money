<?php

declare(strict_types=1);

namespace App\Service\Transaction;

use App\Entity\Category;
use App\Entity\Transaction;
use App\Enum\MovementKindEnum;
use App\Model\DayHistory;
use App\Model\MonthHistory;
use App\Repository\TransactionRepository;
use Psr\Clock\ClockInterface;

final readonly class TransactionHistoryService
{
    public function __construct(
        private TransactionRepository $repository,
        private ClockInterface $clock,
    ) {
    }

    public function month(int $year, int $month, ?MovementKindEnum $type = null): MonthHistory
    {
        $first = $this->clock->now()->setDate($year, $month, 1);
        $last = $first->modify('last day of this month');

        $transactions = $this->repository->findBetween($first, $last);

        $displayed = $transactions;
        if ($type instanceof MovementKindEnum) {
            $displayed = $this->repository->findBetween($first, $last, $type);
        }

        $days = $this->groupByDay($displayed);

        $expense = 0;
        $income = 0;
        foreach ($transactions as $transaction) {
            $signedAmount = $this->signedAmount($transaction);
            if ($signedAmount < 0) {
                $expense -= $signedAmount;
            } elseif ($signedAmount > 0) {
                $income += $signedAmount;
            }
        }

        $balance = $income - $expense;

        return new MonthHistory($first, $expense, $income, $balance, $days);
    }

    private function signedAmount(Transaction $transaction): int
    {
        $date = $transaction->getDate();
        if (!$date instanceof \DateTimeImmutable) {
            return 0;
        }
        $today = $this->clock->now();

        if ($date->format('Y-m-d') > $today->format('Y-m-d')) {
            return 0;
        }

        $amount = $transaction->getAmount();
        if (null === $amount) {
            return 0;
        }

        $category = $transaction->getCategory();

        if (!$category instanceof Category) {
            return 0;
        }

        $type = $category->getType();
        if (MovementKindEnum::DEPENSE === $type) {
            return -$amount;
        }

        return $amount;
    }

    /**
     * @param list<Transaction> $transactions
     *
     * @return list<DayHistory>
     *
     * @throws \DateMalformedStringException
     */
    private function groupByDay(array $transactions): array
    {
        $byDate = [];
        foreach ($transactions as $transaction) {
            $date = $transaction->getDate();
            if (null === $date) {
                continue;
            }

            $byDate[$date->format('Y-m-d')][] = $transaction;
        }

        $days = [];
        foreach ($byDate as $key => $dayTransactions) {
            $totalDay = 0;
            foreach ($dayTransactions as $transaction) {
                $totalDay += $this->signedAmount($transaction);
            }
            $dayHistory = new DayHistory(new \DateTimeImmutable($key), $dayTransactions, $totalDay);
            $days[] = $dayHistory;
        }

        return $days;
    }
}
