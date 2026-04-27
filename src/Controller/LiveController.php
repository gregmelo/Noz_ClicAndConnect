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
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class LiveController extends AbstractController
{
    // Topic Mercure partagé entre le serveur et les clients JS
    private const LIVE_TOPIC = 'https://nozamberieu.fr/live/products';

    #[Route('/live', name: 'app_live_dashboard')]
    #[IsGranted('ROLE_WARRIOR_JUNIOR')]
    public function index(
        ProductRepository $productRepository,
        GlobalStatRepository $globalStatRepository
    ): Response {
        $globalStat = $globalStatRepository->getOrCreate();

        return $this->render('live/dashboard.html.twig', [
            'products'   => $productRepository->findBy([], ['id' => 'DESC']),
            'nextLiveAt' => $globalStat->getNextLiveAt(),
        ]);
    }

    #[Route('/live/schedule', name: 'app_live_schedule', methods: ['POST'])]
    #[IsGranted('ROLE_WARRIOR_JUNIOR')]
    public function schedule(
        Request $request,
        GlobalStatRepository $globalStatRepository,
        EntityManagerInterface $entityManager,
        HubInterface $hub
    ): Response {
        if (!$this->isCsrfTokenValid('schedule_next_live', $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('app_live_dashboard');
        }

        $action = $request->request->get('action', 'set');
        $date   = $request->request->get('next_live_date');
        $time   = $request->request->get('next_live_time');

        $globalStat = $globalStatRepository->getOrCreate();

        if ($action === 'clear') {
            $globalStat->setNextLiveAt(null);
            $entityManager->flush();

            // Notifier les clients
            $hub->publish(new Update(
                self::LIVE_TOPIC,
                json_encode(['event' => 'live_schedule_updated', 'nextLiveAt' => null])
            ));

            $this->addFlash('success', 'Aucun live n\'est désormais programmé.');
            return $this->redirectToRoute('app_live_dashboard');
        }

        if (empty($date) || empty($time)) {
            $this->addFlash('danger', 'Merci de renseigner la date et l\'heure du prochain live.');
            return $this->redirectToRoute('app_live_dashboard');
        }

        try {
            $nextLiveAt = new \DateTimeImmutable($date . ' ' . $time, new \DateTimeZone('Europe/Paris'));
        } catch (\Exception $e) {
            $this->addFlash('danger', 'Format de date/heure invalide.');
            return $this->redirectToRoute('app_live_dashboard');
        }

        $globalStat->setNextLiveAt($nextLiveAt);
        $entityManager->flush();

        // Notifier les clients
        $hub->publish(new Update(
            self::LIVE_TOPIC,
            json_encode(['event' => 'live_schedule_updated', 'nextLiveAt' => $nextLiveAt->format('c')])
        ));

        $this->addFlash('success', sprintf(
            'Prochain live programmé le %s à %s.',
            $nextLiveAt->format('d/m/Y'),
            $nextLiveAt->format('H:i')
        ));

        return $this->redirectToRoute('app_live_dashboard');
    }

    #[Route('/live/activate/{id}', name: 'app_live_product_activate', methods: ['POST'])]
    #[IsGranted('ROLE_WARRIOR_JUNIOR')]
    public function activate(
        Product $product,
        Request $request,
        EntityManagerInterface $entityManager,
        HubInterface $hub
    ): JsonResponse {
        if (!$this->isCsrfTokenValid('live_activate_' . $product->getId(), $request->request->get('_token'))) {
            return $this->json(['error' => 'Jeton CSRF invalide.'], Response::HTTP_FORBIDDEN);
        }

        $product->setIsLive(true);
        $product->setActivatedAt(new \DateTimeImmutable());
        $entityManager->flush();

        // Publier l'événement vers tous les clients abonnés
        $hub->publish(new Update(
            self::LIVE_TOPIC,
            json_encode([
                'event'         => 'product_activated',
                'id'            => $product->getId(),
                'name'          => $product->getName(),
                'description'   => $product->getDescription(),
                'price'         => $product->getPrice(),
                'originalPrice' => $product->getOriginalPrice(),
                'stock'         => $product->getStock(),
                'image'         => $product->getImageFilename(),
            ])
        ));

        return $this->json([
            'success' => true,
            'message' => 'Produit "' . $product->getName() . '" est maintenant en ligne !',
        ]);
    }

    #[Route('/live/deactivate/{id}', name: 'app_live_product_deactivate', methods: ['POST'])]
    #[IsGranted('ROLE_WARRIOR_JUNIOR')]
    public function deactivate(
        Product $product,
        Request $request,
        EntityManagerInterface $entityManager,
        HubInterface $hub
    ): JsonResponse {
        if (!$this->isCsrfTokenValid('live_deactivate_' . $product->getId(), $request->request->get('_token'))) {
            return $this->json(['error' => 'Jeton CSRF invalide.'], Response::HTTP_FORBIDDEN);
        }

        $product->setIsLive(false);
        $entityManager->flush();

        // Notifier tous les clients que ce produit est retiré
        $hub->publish(new Update(
            self::LIVE_TOPIC,
            json_encode([
                'event' => 'product_deactivated',
                'id'    => $product->getId(),
            ])
        ));

        return $this->json([
            'success' => true,
            'message' => 'Produit "' . $product->getName() . '" a été retiré.',
        ]);
    }

    /**
     * Endpoint initial : renvoie les produits déjà en live au chargement de la page.
     * Appelé une seule fois par le JS au démarrage, avant de s'abonner à Mercure.
     */
    #[Route('/api/live/products', name: 'api_live_products', methods: ['GET'])]
    public function getLiveProducts(ProductRepository $productRepository): JsonResponse
    {
        $products = $productRepository->findBy(['isLive' => true], ['id' => 'DESC']);

        $data = array_map(fn(Product $p) => [
            'id'            => $p->getId(),
            'name'          => $p->getName(),
            'description'   => $p->getDescription(),
            'price'         => $p->getPrice(),
            'originalPrice' => $p->getOriginalPrice(),
            'stock'         => $p->getStock(),
            'image'         => $p->getImageFilename(),
        ], $products);

        return $this->json($data);
    }
}