<?php

namespace App\Twig;

use App\Repository\ReservationRepository;
use App\Repository\GlobalStatRepository;
use App\Service\CartService;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    public function __construct(
        private ReservationRepository $reservationRepository,
        private CartService $cartService,
        private GlobalStatRepository $globalStatRepository,
        private EntityManagerInterface $entityManager
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
        try {
            return (float) $this->entityManager->createQuery(
                'SELECT SUM(u.cumulativeRevenue) FROM App\Entity\User u WHERE u.cumulativeRevenue > 0'
            )->getSingleScalarResult() ?? 0.0;
        } catch (\Exception $e) {
            return 0.0;
        }
    }
}