<?php

namespace App\Controller;

use App\Entity\PushSubscription;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/push')]
class PushController extends AbstractController
{
    #[Route('/subscribe', name: 'api_push_subscribe', methods: ['POST'])]
    public function subscribe(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['endpoint'])) {
            return new JsonResponse(['error' => 'Invalid data'], 400);
        }

        $subscription = $entityManager->getRepository(PushSubscription::class)->findOneBy(['endpoint' => $data['endpoint']]);

        if (!$subscription) {
            $subscription = new PushSubscription();
            $subscription->setEndpoint($data['endpoint']);
        }

        $subscription->setPublicKey($data['keys']['p256dh'] ?? '');
        $subscription->setAuthToken($data['keys']['auth'] ?? '');
        $subscription->setUser($this->getUser()); // Can be null for guests if allowed, or restricted to logged in users

        $entityManager->persist($subscription);
        $entityManager->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/unsubscribe', name: 'api_push_unsubscribe', methods: ['POST'])]
    public function unsubscribe(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['endpoint'])) {
            return new JsonResponse(['error' => 'Invalid data'], 400);
        }

        $subscription = $entityManager->getRepository(PushSubscription::class)->findOneBy(['endpoint' => $data['endpoint']]);

        if ($subscription) {
            $entityManager->remove($subscription);
            $entityManager->flush();
        }

        return new JsonResponse(['success' => true]);
    }
}
