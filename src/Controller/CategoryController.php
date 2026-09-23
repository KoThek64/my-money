<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\User;
use App\Form\CategoryEditType;
use App\Form\CategoryType;
use App\Repository\CategoryRepository;
use App\Service\Category\CrudCategoryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

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
        $categories = $this->repository->findAll();

        return $this->render('category/show_all.html.twig', [
            'categories' => $categories,
        ]);
    }

    #[Route('/show/{id}', name: 'show')]
    public function show(Category $category): Response
    {
        return $this->render('category/show.html.twig', [
            'category' => $category,
        ]);
    }

    #[Route('/create', name: 'create')]
    public function create(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $category = new Category();

        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->service->create($user, $category);

            return $this->redirectToRoute('app_home');
        }

        return $this->render('category/create.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/edit/{id}', name: 'edit')]
    public function edit(Category $category, Request $request): Response
    {
        $form = $this->createForm(CategoryEditType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->service->edit();

            return $this->redirectToRoute('app_home');
        }

        return $this->render('category/edit.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/delete/{id}', name: 'delete', methods: ['POST'])]
    #[IsCsrfTokenValid('delete-category')]
    public function delete(Category $category): RedirectResponse
    {
        $this->service->delete($category);

        return $this->redirectToRoute('app_home');
    }
}
