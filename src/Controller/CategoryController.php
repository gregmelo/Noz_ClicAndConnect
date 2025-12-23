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

#[Route('/admin/category')]
#[IsGranted('ROLE_ADMIN')]
class CategoryController extends AbstractController
{
    #[Route('/', name: 'app_admin_category_index', methods: ['GET'])]
    public function index(CategoryRepository $categoryRepository): Response
    {
        return $this->render('admin/category/index.html.twig', [
            'categories' => $categoryRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_admin_category_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $category = new Category();
        if ($request->isMethod('POST')) {
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

    #[Route('/{id}/edit', name: 'app_admin_category_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Category $category, EntityManagerInterface $entityManager): Response
    {
        if ($request->isMethod('POST')) {
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
