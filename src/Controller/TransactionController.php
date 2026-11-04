<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Category;
use App\Entity\Transaction;
use App\Enum\MovementKindEnum;
use App\Form\TransactionType;
use App\Security\Voter\OwnershipVoter;
use App\Service\Transaction\CrudTransactionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/transaction', name: 'app_transaction_')]
final class TransactionController extends AbstractController
{
    public function __construct(
        private readonly CrudTransactionService $service,
    ) {
    }

    #[Route('/create/{type}', name: 'create')]
    public function create(Request $request, string $type = 'depense'): Response
    {
        $enumType = MovementKindEnum::tryFrom($type);
        if (null === $enumType) {
            throw $this->createNotFoundException();
        }
        $transaction = $this->service->prepare();

        $form = $this->createForm(TransactionType::class, $transaction, ['type' => $enumType]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->service->create($transaction);

            return $this->redirectToRoute('app_home'); // TODO à changer vers l'historique en phase 6
        }

        return $this->render('transaction/create.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/edit/{id}', name: 'edit')]
    #[IsGranted(OwnershipVoter::EDIT, 'transaction')]
    public function edit(Transaction $transaction, Request $request): Response
    {
        $category = $transaction->getCategory();
        if (!$category instanceof Category) {
            throw $this->createNotFoundException();
        }

        $enumType = $category->getType();
        if (!$enumType instanceof MovementKindEnum) {
            throw $this->createNotFoundException();
        }

        $form = $this->createForm(TransactionType::class, $transaction, ['type' => $enumType]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->service->edit();

            return $this->redirectToRoute('app_home');
        }

        return $this->render('transaction/edit.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/delete/{id}', name: 'delete', methods: ['POST'])]
    #[IsCsrfTokenValid('delete-transaction')]
    #[IsGranted(OwnershipVoter::DELETE, 'transaction')]
    public function delete(Transaction $transaction): RedirectResponse
    {
        $this->service->delete($transaction);

        return $this->redirectToRoute('app_home');
    }
}
