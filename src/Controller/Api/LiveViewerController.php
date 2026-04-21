<?php

namespace App\Controller\Api;

use App\Entity\LiveSession;
use App\Repository\LiveSessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/live')]
class LiveViewerController extends AbstractController
{
    private const VIEWER_TTL = 60;
    private const REDIS_VIEWERS_KEY = 'live_viewers';
    private const REDIS_TOTAL_KEY = 'live_total_viewers';

    private function getRedis(): \Redis
    {
        /** @var \Redis $redis */
        $redis = new \Redis();
        $redis->connect('127.0.0.1', 6379);
        return $redis;
    }

    #[Route('/ping', name: 'api_live_ping', methods: ['POST'])]
    public function ping(
        Request $request,
        LiveSessionRepository $liveSessionRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $session = $request->getSession();
        $viewerId = $session->getId();

        if (!$viewerId) {
            return $this->json(['ok' => false]);
        }

        $redis = $this->getRedis();
        $now = time();

        $redis->hSet(self::REDIS_VIEWERS_KEY, $viewerId, $now);

        // Nettoie les viewers expirés
        $allViewers = $redis->hGetAll(self::REDIS_VIEWERS_KEY);
        foreach ($allViewers as $id => $timestamp) {
            if ($now - (int)$timestamp > self::VIEWER_TTL) {
                $redis->hDel(self::REDIS_VIEWERS_KEY, $id);
            }
        }

        $currentViewers = $redis->hLen(self::REDIS_VIEWERS_KEY);
        $redis->sAdd(self::REDIS_TOTAL_KEY, $viewerId);
        $totalViewers = $redis->sCard(self::REDIS_TOTAL_KEY);

        // Met à jour la live_session en cours
        $liveSession = $liveSessionRepository->findCurrentSession();
        if ($liveSession) {
            if ($currentViewers > $liveSession->getMaxViewers()) {
                $liveSession->setMaxViewers($currentViewers);
            }
            $liveSession->setTotalViewers($totalViewers);
            $entityManager->flush();
        }

        return $this->json([
            'ok' => true,
            'current' => $currentViewers,
            'total' => $totalViewers,
        ]);
    }

    #[Route('/viewers', name: 'api_live_viewers', methods: ['GET'])]
    public function viewers(): JsonResponse
    {
        $redis = $this->getRedis();
        $now = time();
        $allViewers = $redis->hGetAll(self::REDIS_VIEWERS_KEY) ?: [];

        $active = 0;
        foreach ($allViewers as $id => $timestamp) {
            if ($now - (int)$timestamp <= self::VIEWER_TTL) {
                $active++;
            } else {
                $redis->hDel(self::REDIS_VIEWERS_KEY, $id);
            }
        }

        $total = $redis->sCard(self::REDIS_TOTAL_KEY) ?: 0;

        return $this->json([
            'current' => $active,
            'total' => $total,
        ]);
    }

    #[Route('/session/start', name: 'api_live_session_start', methods: ['POST'])]
    public function startSession(
        LiveSessionRepository $liveSessionRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $existing = $liveSessionRepository->findCurrentSession();
        if ($existing) {
            return $this->json(['ok' => true, 'session_id' => $existing->getId(), 'existing' => true]);
        }

        $redis = $this->getRedis();
        $redis->del(self::REDIS_VIEWERS_KEY);
        $redis->del(self::REDIS_TOTAL_KEY);

        $session = new LiveSession();
        $entityManager->persist($session);
        $entityManager->flush();

        return $this->json([
            'ok' => true,
            'session_id' => $session->getId(),
            'existing' => false,
        ]);
    }
}