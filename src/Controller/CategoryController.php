<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Category;
use App\Form\CategoryEditType;
use App\Form\CategoryType;
use App\Repository\CategoryRepository;
use App\Security\Voter\OwnershipVoter;
use App\Service\Category\CrudCategoryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/category', name: 'app_category_')]
final class CategoryController extends AbstractController
{
    public function __construct(
        private readonly CrudCategoryService $service,
        private readonly CategoryRepository $repository,
    ) {
    }

    #[Route('/show-all', name: 'show_all')]
    public function showAll(): Response
    {
        $categories = $this->repository->findActive();

        return $this->render('category/show_all.html.twig', [
            'categories' => $categories,
        ]);
    }

    #[Route('/show/{id}', name: 'show')]
    #[IsGranted(OwnershipVoter::VIEW, 'category')]
    public function show(Category $category): Response
    {
        return $this->render('category/show.html.twig', [
            'category' => $category,
        ]);
    }

    #[Route('/create', name: 'create')]
    public function create(Request $request): Response
    {
        $category = new Category();

        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->service->create($category);

            return $this->redirectToRoute('app_category_show_all');
        }

        return $this->render('category/create.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/edit/{id}', name: 'edit')]
    #[IsGranted(OwnershipVoter::EDIT, 'category')]
    public function edit(Category $category, Request $request): Response
    {
        if ($category->isArchived()) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(
            CategoryEditType::class,
            $category,
            ['type_editable' => !$this->service->isUsed($category)]
        );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->service->edit();

            return $this->redirectToRoute('app_category_show_all');
        }

        return $this->render('category/edit.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/delete/{id}', name: 'delete', methods: ['POST'])]
    #[IsCsrfTokenValid('delete-category')]
    #[IsGranted(OwnershipVoter::DELETE, 'category')]
    public function delete(Category $category): RedirectResponse
    {
        if ($category->isArchived()) {
            throw $this->createAccessDeniedException();
        }

        $this->service->delete($category);

        return $this->redirectToRoute('app_category_show_all');
    }
}
