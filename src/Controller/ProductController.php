<?php

namespace App\Controller;

use App\Entity\Product;
use App\Form\ProductType;
use App\Repository\ProductRepository;
use App\Service\ActivityLogger;
use App\Service\FileUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * ProductController
 * 
 * Administrative controller for managing the product catalog.
 * Accessible to users with ROLE_WARRIOR_JUNIOR or higher.
 */
#[Route('/product')]
#[IsGranted('ROLE_WARRIOR_JUNIOR')]
final class ProductController extends AbstractController
{
    /**
     * List all products (Admin view)
     *
     * @param ProductRepository $productRepository
     * @return Response
     */
    #[Route(name: 'app_product_index', methods: ['GET'])]
    public function index(ProductRepository $productRepository): Response
    {
        return $this->render('product/index.html.twig', [
            'products' => $productRepository->findBy([], ['id' => 'DESC']),
        ]);
    }

    /**
     * Create a new product
     *
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param FileUploader $fileUploader
     * @param ActivityLogger $logger
     * @return Response
     */
    #[Route('/new', name: 'app_product_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, FileUploader $fileUploader, ActivityLogger $logger): Response
    {
        $product = new Product();
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Handle main image upload
            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                try {
                    $newFilename = $fileUploader->upload($imageFile);
                    $product->setImageFilename($newFilename);
                } catch (\Exception $e) {
                    $this->addFlash('danger', 'Erreur lors de l\'upload de l\'image principale : ' . $e->getMessage());
                }
            }

            // Handle extra images upload
            $extraImages = $form->get('extraImages')->getData() ?? [];
            foreach ($extraImages as $uploadedFile) {
                if (!$uploadedFile) {
                    continue;
                }
                try {
                    $filename = $fileUploader->upload($uploadedFile);
                    $product->addExtraImage($filename);
                } catch (\Exception $e) {
                    $this->addFlash('danger', 'Erreur lors de l\'upload d\'une image supplémentaire : ' . $e->getMessage());
                }
            }

            $product->setCreatedBy($this->getUser());
            $entityManager->persist($product);
            $entityManager->flush();

            // Audit Log
            $logger->logProductCreated($this->getUser(), $product->getId(), $product->getName());

            $this->addFlash('success', 'Produit créé avec succès !');
            return $this->redirectToRoute('app_product_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('product/new.html.twig', [
            'product' => $product,
            'form' => $form,
        ]);
    }

    /**
     * View product details (Admin view)
     *
     * @param Product $product
     * @return Response
     */
    #[Route('/{id}', name: 'app_product_show', methods: ['GET'])]
    public function show(Product $product): Response
    {
        return $this->render('product/show.html.twig', [
            'product' => $product,
        ]);
    }

    /**
     * Edit an existing product
     *
     * @param Request $request
     * @param Product $product
     * @param EntityManagerInterface $entityManager
     * @param FileUploader $fileUploader
     * @param ActivityLogger $logger
     * @return Response
     */
    #[Route('/{id}/edit', name: 'app_product_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Product $product, EntityManagerInterface $entityManager, FileUploader $fileUploader, ActivityLogger $logger): Response
    {
        $oldPrice = $product->getPrice();
        $oldStock = $product->getStock();
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Handle main image upload
            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                try {
                    $newFilename = $fileUploader->upload($imageFile);
                    $product->setImageFilename($newFilename);
                } catch (\Exception $e) {
                    $this->addFlash('danger', 'Erreur lors de l\'upload de l\'image principale : ' . $e->getMessage());
                }
            }

            // Handle extra images upload (append to existing ones)
            $extraImages = $form->get('extraImages')->getData() ?? [];
            foreach ($extraImages as $uploadedFile) {
                if (!$uploadedFile) {
                    continue;
                }
                try {
                    $filename = $fileUploader->upload($uploadedFile);
                    $product->addExtraImage($filename);
                } catch (\Exception $e) {
                    $this->addFlash('danger', 'Erreur lors de l\'upload d\'une image supplémentaire : ' . $e->getMessage());
                }
            }

            // Automatic Price Drop Logic
            $newPrice = $product->getPrice();
            $newStock = $product->getStock();

            // If original price is not manually set by the user
            if (!$form->get('originalPrice')->getData()) {
                if ($newPrice < $oldPrice) {
                    // Price dropped: Set original price to the old price
                    $product->setOriginalPrice($oldPrice);
                } elseif ($newPrice >= $oldPrice) {
                    // Price increased or same: Remove original price (not a promotion anymore)
                    $product->setOriginalPrice(null);
                }
            }

            $entityManager->flush();

            // Audit Log
            $logger->logProductUpdated($this->getUser(), $product->getId(), $product->getName(), $oldPrice, $newPrice, $oldStock, $newStock);

            $this->addFlash('success', 'Produit modifié avec succès !');
            return $this->redirectToRoute('app_product_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('product/edit.html.twig', [
            'product' => $product,
            'form' => $form,
        ]);
    }

    /**
     * Delete a product
     *
     * @param Request $request
     * @param Product $product
     * @param EntityManagerInterface $entityManager
     * @param ActivityLogger $logger
     * @return Response
     */
    #[Route('/{id}', name: 'app_product_delete', methods: ['POST'])]
    public function delete(Request $request, Product $product, EntityManagerInterface $entityManager, ActivityLogger $logger): Response
    {
        if ($this->isCsrfTokenValid('delete' . $product->getId(), $request->getPayload()->getString('_token'))) {
            $logger->logProductDeleted($this->getUser(), $product->getId(), $product->getName());
            // Supprimer les reservation_items liés avant suppression du produit
            $conn = $entityManager->getConnection();
            $conn->executeStatement("DELETE FROM reservation_item WHERE product_id = ?", [$product->getId()]);
            $entityManager->remove($product);
            $entityManager->flush();
        }
        return $this->redirectToRoute('app_product_index', [], Response::HTTP_SEE_OTHER);
    }
}
