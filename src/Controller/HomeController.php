<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(Request $request, ProductRepository $productRepository, \App\Repository\CategoryRepository $categoryRepository): Response
    {
        $search = $request->query->get('search', '');
        $minPrice = $request->query->get('min_price', '');
        $maxPrice = $request->query->get('max_price', '');
        $availability = $request->query->get('availability', '');
        $sort = $request->query->get('sort', 'newest');
        $categoryId = $request->query->get('category', '');
        
        $qb = $productRepository->createQueryBuilder('p');

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
        $totalPages = ceil($totalProducts / $limit);
        $paginatedProducts = array_slice($products, $offset, $limit);

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
        ]);
    }

    #[Route('/product/{id}', name: 'app_product_show_public', methods: ['GET'])]
    public function show(\App\Entity\Product $product): Response
    {
        return $this->render('home/show.html.twig', [
            'product' => $product,
        ]);
    }
}
