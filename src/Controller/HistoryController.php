<?php

declare(strict_types=1);

namespace App\Controller;

use App\Enum\MovementKindEnum;
use App\Service\Transaction\TransactionHistoryService;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/history', name: 'app_history_')]
final class HistoryController extends AbstractController
{
    public function __construct(
        private readonly TransactionHistoryService $service,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route('', name: 'current')]
    public function current(): RedirectResponse
    {
        $now = $this->clock->now();

        return $this->redirectToRoute('app_history_month', [
            'year' => $now->format('Y'),
            'month' => $now->format('m'),
        ]);
    }

    #[Route('/{year}/{month}', name: 'month', requirements: ['year' => '\d{4}', 'month' => '0[1-9]|1[0-2]'])]
    public function month(
        int $year,
        string $month,
        #[MapQueryParameter]
        ?MovementKindEnum $type = null,
    ): Response {
        $monthHistory = $this->service->month($year, (int) $month, $type);

        return $this->render('history/index.html.twig', [
            'monthHistory' => $monthHistory,
            'type' => $type,
        ]);
    }
}
