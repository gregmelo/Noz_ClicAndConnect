<?php

namespace App\Controller;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * CategoryController
 * 
 * Administrative controller for managing product categories.
 * Accessible only to users with ROLE_WARRIOR or higher.
 */
#[Route('/admin/category')]
#[IsGranted('ROLE_WARRIOR')]
class CategoryController extends AbstractController
{
    /**
     * List all categories
     *
     * @param CategoryRepository $categoryRepository
     * @return Response
     */
    #[Route('/', name: 'app_admin_category_index', methods: ['GET'])]
    public function index(CategoryRepository $categoryRepository): Response
    {
        return $this->render('admin/category/index.html.twig', [
            'categories' => $categoryRepository->findAll(),
        ]);
    }

    /**
     * Create a new category
     *
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    #[Route('/new', name: 'app_admin_category_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $category = new Category();
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('admin_category_new', $request->request->get('_token'))) {
                $this->addFlash('danger', 'Jeton de sécurité invalide.');
                return $this->redirectToRoute('app_admin_category_index');
            }
            $name = $request->request->get('name');
            if ($name) {
                $category->setName($name);
                $entityManager->persist($category);
                $entityManager->flush();

                $this->addFlash('success', 'Catégorie créée avec succès.');
                return $this->redirectToRoute('app_admin_category_index');
            }
        }

        return $this->render('admin/category/new.html.twig', [
            'category' => $category,
        ]);
    }

    /**
     * Edit an existing category
     *
     * @param Request $request
     * @param Category $category
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    #[Route('/{id}/edit', name: 'app_admin_category_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Category $category, EntityManagerInterface $entityManager): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('admin_category_edit', $request->request->get('_token'))) {
                $this->addFlash('danger', 'Jeton de sécurité invalide.');
                return $this->redirectToRoute('app_admin_category_index');
            }
            $name = $request->request->get('name');
            if ($name) {
                $category->setName($name);
                $entityManager->flush();

                $this->addFlash('success', 'Catégorie mise à jour.');
                return $this->redirectToRoute('app_admin_category_index');
            }
        }

        return $this->render('admin/category/edit.html.twig', [
            'category' => $category,
        ]);
    }

    /**
     * Delete a category
     * Prevents deletion if the category is not empty.
     *
     * @param Request $request
     * @param Category $category
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    #[Route('/{id}', name: 'app_admin_category_delete', methods: ['POST'])]
    public function delete(Request $request, Category $category, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$category->getId(), $request->request->get('_token'))) {
            // Check if category has products
            if (!$category->getProducts()->isEmpty()) {
                $this->addFlash('danger', 'Impossible de supprimer cette catégorie car elle contient des produits.');
                return $this->redirectToRoute('app_admin_category_index');
            }
            
            $entityManager->remove($category);
            $entityManager->flush();
            $this->addFlash('success', 'Catégorie supprimée.');
        }

        return $this->redirectToRoute('app_admin_category_index');
    }
}
