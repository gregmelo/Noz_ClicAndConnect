<?php

namespace App\Twig;

use App\Repository\ReservationRepository;
use App\Service\CartService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    public function __construct(
        private ReservationRepository $reservationRepository,
        private CartService $cartService
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
        return $this->reservationRepository->count(['status' => ['ACTIVE', 'READY']]);
    }

    public function getStoreRevenue(): float
    {
        return $this->reservationRepository->createQueryBuilder('r')
            ->select('SUM(ri.quantity * p.price)')
            ->join('r.reservationItems', 'ri')
            ->join('ri.product', 'p')
            ->where('r.status = :status')
            ->setParameter('status', 'COLLECTED')
            ->getQuery()
            ->getSingleScalarResult() ?? 0.0;
    }
}
