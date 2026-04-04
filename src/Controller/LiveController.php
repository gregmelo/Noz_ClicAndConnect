<?php

namespace App\Controller;

use App\Entity\Product;
use App\Repository\ProductRepository;
use App\Repository\GlobalStatRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * LiveController
 * 
 * Manages the "Live Shopping" features.
 * Accessible to ROLE_EMPLOYEE for the dashboard and activation/deactivation.
 * Accessible to all for the SSE stream.
 */
class LiveController extends AbstractController
{
    /**
     * Dashboard for employees to manage products during a live session.
     */
    #[Route('/live', name: 'app_live_dashboard')]
    #[IsGranted('ROLE_EMPLOYEE')]
    public function index(ProductRepository $productRepository, GlobalStatRepository $globalStatRepository): Response
    {
        $globalStat = $globalStatRepository->getOrCreate();

        return $this->render('live/dashboard.html.twig', [
            'products' => $productRepository->findBy([], ['isLive' => 'DESC', 'name' => 'ASC']),
            'nextLiveAt' => $globalStat->getNextLiveAt(),
        ]);
    }

    /**
     * Schedule or clear the next live date/time.
     */
    #[Route('/live/schedule', name: 'app_live_schedule', methods: ['POST'])]
    #[IsGranted('ROLE_EMPLOYEE')]
    public function schedule(Request $request, GlobalStatRepository $globalStatRepository, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('schedule_next_live', $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('app_live_dashboard');
        }

        $action = $request->request->get('action', 'set');
        $date = $request->request->get('next_live_date');
        $time = $request->request->get('next_live_time');

        $globalStat = $globalStatRepository->getOrCreate();

        if ($action === 'clear') {
            $globalStat->setNextLiveAt(null);
            $entityManager->flush();
            $this->addFlash('success', 'Aucun live n\'est désormais programmé.');

            return $this->redirectToRoute('app_live_dashboard');
        }

        if (empty($date) || empty($time)) {
            $this->addFlash('danger', 'Merci de renseigner la date et l\'heure du prochain live.');
            return $this->redirectToRoute('app_live_dashboard');
        }

        try {
            $nextLiveAt = new \DateTimeImmutable($date . ' ' . $time);
        } catch (\Exception $e) {
            $this->addFlash('danger', 'Format de date/heure invalide.');
            return $this->redirectToRoute('app_live_dashboard');
        }

        $globalStat->setNextLiveAt($nextLiveAt);
        $entityManager->flush();

        $this->addFlash('success', sprintf('Prochain live programmé le %s à %s.', $nextLiveAt->format('d/m/Y'), $nextLiveAt->format('H:i')));

        return $this->redirectToRoute('app_live_dashboard');
    }

    /**
     * Activate a product for the live session.
     */
    #[Route('/live/activate/{id}', name: 'app_live_product_activate', methods: ['POST'])]
    #[IsGranted('ROLE_EMPLOYEE')]
    public function activate(Product $product, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        if (!$this->isCsrfTokenValid('live_activate_' . $product->getId(), $request->request->get('_token'))) {
            return $this->json(['error' => 'Jeton CSRF invalide.'], Response::HTTP_FORBIDDEN);
        }

        $product->setIsLive(true);
        $product->setActivatedAt(new \DateTimeImmutable());
        $entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Produit "' . $product->getName() . '" est maintenant en ligne !',
            'product' => [
                'id' => $product->getId(),
                'name' => $product->getName(),
                'stock' => $product->getStock()
            ]
        ]);
    }

    /**
     * Deactivate a product.
     */
    #[Route('/live/deactivate/{id}', name: 'app_live_product_deactivate', methods: ['POST'])]
    #[IsGranted('ROLE_EMPLOYEE')]
    public function deactivate(Product $product, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        if (!$this->isCsrfTokenValid('live_deactivate_' . $product->getId(), $request->request->get('_token'))) {
            return $this->json(['error' => 'Jeton CSRF invalide.'], Response::HTTP_FORBIDDEN);
        }

        $product->setIsLive(false);
        $entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Produit "' . $product->getName() . '" a été retiré.',
            'product' => [
                'id' => $product->getId()
            ]
        ]);
    }

    /**
     * SSE Stream for real-time updates.
     * Clients (Home page) will listen to this to know when products are added/removed
     * or when stocks change.
     */
    #[Route('/api/live/stream', name: 'api_live_stream')]
    public function sseStream(ProductRepository $productRepository): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($productRepository) {
            $lastData = '';

            while (true) {
                // Fetch all live products and their stocks
                $liveProducts = $productRepository->findBy(['isLive' => true]);
                
                $data = [];
                foreach ($liveProducts as $p) {
                    $data[] = [
                        'id' => $p->getId(),
                        'name' => $p->getName(),
                        'description' => $p->getDescription(),
                        'price' => $p->getPrice(),
                        'originalPrice' => $p->getOriginalPrice(),
                        'stock' => $p->getStock(),
                        'image' => $p->getImageFilename()
                    ];
                }

                $jsonContent = json_encode($data);

                // Only send update if data has changed
                if ($jsonContent !== $lastData) {
                    echo "data: " . $jsonContent . "\n\n";
                    $lastData = $jsonContent;
                    ob_flush();
                    flush();
                }

                // Break loop if connection is closed
                if (connection_aborted()) {
                    break;
                }

                // Wait 2 seconds before next check to preserve server resources
                sleep(2);
            }
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('Connection', 'keep-alive');
        $response->headers->set('X-Accel-Buffering', 'no'); // Important for Nginx/Proxy buffer

        return $response;
    }
}
