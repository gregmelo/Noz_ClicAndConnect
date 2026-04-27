<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SitemapController extends AbstractController
{
    #[Route('/sitemap.xml', name: 'app_sitemap', methods: ['GET'])]
    public function index(ProductRepository $productRepository): Response
    {
        $liveProducts = $productRepository->findBy(['isLive' => true]);

        $staticUrls = [
            ['loc' => '/',                          'priority' => '1.0', 'changefreq' => 'daily'],
            ['loc' => '/mentions-legales',          'priority' => '0.3', 'changefreq' => 'yearly'],
            ['loc' => '/politique-confidentialite', 'priority' => '0.3', 'changefreq' => 'yearly'],
            ['loc' => '/cgv',                       'priority' => '0.3', 'changefreq' => 'yearly'],
        ];

        $response = $this->render('sitemap/index.xml.twig', [
            'staticUrls'   => $staticUrls,
            'liveProducts' => $liveProducts,
        ]);

        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');

        return $response;
    }
}
