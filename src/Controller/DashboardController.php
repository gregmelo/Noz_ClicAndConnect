<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * DashboardController
 * 
 * Provides comprehensive statistics and real-time monitoring for employees/admins.
 * Displays stock alerts, revenue tracking, and urgent reservations.
 */
#[Route('/dashboard')]
#[IsGranted('ROLE_EMPLOYEE')]
class DashboardController extends AbstractController
{
    /**
     * Main dashboard view
     *
     * @param ProductRepository $productRepository
     * @param ReservationRepository $reservationRepository
     * @param \App\Repository\GlobalStatRepository $globalStatRepository
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    #[Route('/', name: 'app_dashboard')]
    public function index(
        ProductRepository $productRepository,
        ReservationRepository $reservationRepository,
        \App\Repository\GlobalStatRepository $globalStatRepository,
        EntityManagerInterface $entityManager
    ): Response {
        // Statistiques produits
        $totalProducts = $productRepository->count([]);
        $productsInStock = $productRepository->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.stock > 0')
            ->getQuery()
            ->getSingleScalarResult();
        $productsOutOfStock = $totalProducts - $productsInStock;

        // Global Stats (Archives)
        $globalStat = $globalStatRepository->getOrCreate();

        // Statistiques réservations (Live + Archives)
        $activeReservations = $reservationRepository->count(['status' => 'ACTIVE']);
        $readyReservations = $reservationRepository->count(['status' => 'READY']);
        
        // Expired = Live Expired + Archived Expired
        $liveExpired = $reservationRepository->count(['status' => 'EXPIRED']); // Only checks EXPIRED status, check if others need inclusion? 
        // Note: The "Expired" section in employee list includes 'CANCELLED' etc. 
        // But dashboard usually tracks specific states. Let's keep strict status matching for now unless user asks.
        $expiredReservations = $liveExpired + $globalStat->getTotalExpiredCount();

        // Collected = Live Collected + Archived Collected
        $liveCollected = $reservationRepository->count(['status' => 'COLLECTED']);
        $collectedReservations = $liveCollected + $globalStat->getTotalCollectedCount();
        
        // Calcul du CA total (Live + Archives)
        $liveRevenue = $reservationRepository->createQueryBuilder('r')
            ->select('SUM(ri.quantity * ri.price)')
            ->join('r.reservationItems', 'ri')
            ->where('r.status = :status')
            ->setParameter('status', 'COLLECTED')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;
            
        $totalRevenue = $liveRevenue + $globalStat->getTotalRevenue();

        // Réservations à récupérer aujourd'hui
        $today = new \DateTimeImmutable();
        $tomorrow = $today->modify('+1 day');
        
        $reservationsToday = $reservationRepository->createQueryBuilder('r')
            ->where('r.status = :status')
            ->andWhere('r.expiresAt >= :today')
            ->andWhere('r.expiresAt < :tomorrow')
            ->setParameter('status', 'READY')
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->getQuery()
            ->getResult();

        // Réservations urgentes (< 6h)
        $urgentTime = $today->modify('+6 hours');
        $urgentReservations = $reservationRepository->createQueryBuilder('r')
            ->where('r.status = :status')
            ->andWhere('r.expiresAt < :urgentTime')
            ->andWhere('r.expiresAt > :now')
            ->setParameter('status', 'ACTIVE')
            ->setParameter('urgentTime', $urgentTime)
            ->setParameter('now', $today)
            ->getQuery()
            ->getResult();

        // Best Sellers Leaderboard (Super Admin / Dev only)
        $bestSellers = [];
        if ($this->isGranted('ROLE_SUPER_ADMIN') || $this->isGranted('ROLE_DEV')) {
            $bestSellers = $entityManager->getRepository(\App\Entity\ReservationItem::class)->createQueryBuilder('ri')
                ->select('u.firstName', 'u.lastName', 'SUM(ri.quantity * ri.price) as revenue', 'SUM(ri.quantity) as itemsSold')
                ->join('ri.product', 'p')
                ->join('p.createdBy', 'u')
                ->join('ri.reservation', 'r')
                ->where('r.status = :status')
                ->setParameter('status', 'COLLECTED')
                ->groupBy('u.id')
                ->orderBy('revenue', 'DESC')
                ->setMaxResults(5)
                ->getQuery()
                ->getResult();
        }

        // Produits en stock bas (< 3 articles)
        $lowStockProducts = $productRepository->createQueryBuilder('p')
            ->where('p.stock <= 3')
            ->andWhere('p.stock > 0')
            ->orderBy('p.stock', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('dashboard/index.html.twig', [
            'totalProducts' => $totalProducts,
            'productsInStock' => $productsInStock,
            'productsOutOfStock' => $productsOutOfStock,
            'activeReservations' => $activeReservations,
            'readyReservations' => $readyReservations,
            'expiredReservations' => $expiredReservations,
            'collectedReservations' => $collectedReservations,
            'reservationsToday' => $reservationsToday,
            'urgentReservations' => $urgentReservations,
            'totalRevenue' => $totalRevenue,
            'bestSellers' => $bestSellers,
            'lowStockProducts' => $lowStockProducts,
        ]);
    }
}
