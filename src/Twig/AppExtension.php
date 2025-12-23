<?php

namespace App\Twig;

use App\Repository\ReservationRepository;
use App\Repository\GlobalStatRepository;
use App\Service\CartService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    public function __construct(
        private ReservationRepository $reservationRepository,
        private CartService $cartService,
        private GlobalStatRepository $globalStatRepository
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('store_revenue', [$this, 'getStoreRevenue']),
            new TwigFunction('cart_count', [$this, 'getCartCount']),
            new TwigFunction('store_todo_count', [$this, 'getStoreTodoCount']),
        ];
    }

    public function getCartCount(): int
    {
        return $this->cartService->getTotalQuantity();
    }

    public function getStoreTodoCount(): int
    {
        return $this->reservationRepository->createQueryBuilder('r')
            ->select('count(r.id)')
            ->where('r.status = :status')
            ->andWhere('r.expiresAt > :now')
            ->setParameter('status', 'ACTIVE')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getStoreRevenue(): float
    {
        // 1. Live Revenue from existing reservations
        $liveRevenue = 0.0;
        
        try {
            $liveRevenue = (float) $this->reservationRepository->createQueryBuilder('r')
                ->select('SUM(ri.quantity * p.price)')
                ->join('r.reservationItems', 'ri')
                ->join('ri.product', 'p')
                ->where('r.status = :status')
                ->setParameter('status', 'COLLECTED')
                ->getQuery()
                ->getSingleScalarResult();
        } catch (\Exception $e) {
            // Ignore if null
        }

        // 2. Archived Revenue from GlobalStat
        $archivedRevenue = 0.0;
        try {
            $globalStat = $this->globalStatRepository->getOrCreate();
            $archivedRevenue = $globalStat->getTotalRevenue();
        } catch (\Exception $e) {
            // Fallback if table doesn't exist yet or other error
        }

        return $liveRevenue + $archivedRevenue;
    }
}
