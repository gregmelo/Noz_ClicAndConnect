<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use App\Repository\GlobalStatRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * HomeController
 * 
 * Handles public-facing landing pages, product listings with search/filtering,
 * and individual product detail views.
 */
class HomeController extends AbstractController
{
    /**
     * Public landing page & search
     *
     * @param Request $request
     * @param ProductRepository $productRepository
     * @param \App\Repository\CategoryRepository $categoryRepository
     * @return Response
     */
    #[Route('/', name: 'app_home')]
    public function index(Request $request, ProductRepository $productRepository, \App\Repository\CategoryRepository $categoryRepository, GlobalStatRepository $globalStatRepository): Response
    {
        $search = $request->query->get('search', '');
        $minPrice = $request->query->get('min_price', '');
        $maxPrice = $request->query->get('max_price', '');
        $availability = $request->query->get('availability', '');
        $sort = $request->query->get('sort', 'newest');
        $categoryId = $request->query->get('category', '');
        
        $qb = $productRepository->createQueryBuilder('p');

        // Live only filter
        $qb->andWhere('p.isLive = true');

        // Category filter
        if ($categoryId) {
            $qb->andWhere('p.category = :categoryId')
               ->setParameter('categoryId', $categoryId);
        }

        // Search filter
        if ($search) {
            $qb->andWhere('p.name LIKE :search OR p.description LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        // Price filters
        if ($minPrice !== '') {
            $qb->andWhere('p.price >= :minPrice')
               ->setParameter('minPrice', (float)$minPrice);
        }

        if ($maxPrice !== '') {
            $qb->andWhere('p.price <= :maxPrice')
               ->setParameter('maxPrice', (float)$maxPrice);
        }

        // Availability filter
        if ($availability === 'in_stock') {
            $qb->andWhere('p.stock > 0');
        } elseif ($availability === 'out_of_stock') {
            $qb->andWhere('p.stock = 0');
        } elseif ($availability === 'promotion') {
            $qb->andWhere('p.originalPrice IS NOT NULL')
               ->andWhere('p.originalPrice > p.price');
        }

        // Sorting
        switch ($sort) {
            case 'price_asc':
                $qb->orderBy('p.price', 'ASC');
                break;
            case 'price_desc':
                $qb->orderBy('p.price', 'DESC');
                break;
            case 'name_asc':
                $qb->orderBy('p.name', 'ASC');
                break;
            case 'oldest':
                $qb->orderBy('p.createdAt', 'ASC');
                break;
            case 'newest':
            default:
                $qb->orderBy('p.createdAt', 'DESC');
                break;
        }

        $products = $qb->getQuery()->getResult();

        // Pagination
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 9; // 3x3 grid
        $offset = ($page - 1) * $limit;

        $totalProducts = count($products);
        $totalPages = (int) ceil($totalProducts / $limit);
        $paginatedProducts = array_slice($products, $offset, $limit);

        // Indicate if a live is currently running (at least one product en ligne)
        $liveInProgress = $totalProducts > 0;

        // Next live scheduling (if configured)
        $globalStat = $globalStatRepository->getOrCreate();
        $nextLiveAt = $globalStat->getNextLiveAt();
        $now = new \DateTimeImmutable('now');
        if ($nextLiveAt && $nextLiveAt <= $now) {
            // Si la date est passée, on ne l'affiche plus comme "prochain live"
            $nextLiveAt = null;
        }

        return $this->render('home/index.html.twig', [
            'products' => $paginatedProducts,
            'categories' => $categoryRepository->findBy([], ['name' => 'ASC']),
            'currentCategory' => $categoryId,
            'search' => $search,
            'min_price' => $minPrice,
            'max_price' => $maxPrice,
            'availability' => $availability,
            'sort' => $sort,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'liveInProgress' => $liveInProgress,
            'nextLiveAt' => $nextLiveAt,
        ]);
    }

    /**
     * Public product details view (front)
     *
     * @param \App\Entity\Product $product
     * @return Response
     */
    #[Route('/produit/{id}', name: 'app_product_show_public', methods: ['GET'])]
    public function show(\App\Entity\Product $product): Response
    {
        if (!$product->isLive()) {
            $this->addFlash('danger', 'Ce produit n\'est plus disponible car le live est terminé ou n\'a pas encore commencé.');
            return $this->redirectToRoute('app_home');
        }

        return $this->render('home/show.html.twig', [
            'product' => $product,
        ]);
    }
}
