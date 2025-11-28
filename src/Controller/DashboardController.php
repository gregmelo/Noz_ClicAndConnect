<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard')]
#[IsGranted('ROLE_EMPLOYEE')]
class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_dashboard')]
    public function index(
        ProductRepository $productRepository,
        ReservationRepository $reservationRepository,
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

        // Statistiques réservations
        $activeReservations = $reservationRepository->count(['status' => 'ACTIVE']);
        $readyReservations = $reservationRepository->count(['status' => 'READY']);
        $expiredReservations = $reservationRepository->count(['status' => 'EXPIRED']);
        $collectedReservations = $reservationRepository->count(['status' => 'COLLECTED']);
        
        // Calcul du CA total des réservations récupérées
        $totalRevenue = $reservationRepository->createQueryBuilder('r')
            ->select('SUM(ri.quantity * p.price)')
            ->join('r.reservationItems', 'ri')
            ->join('ri.product', 'p')
            ->where('r.status = :status')
            ->setParameter('status', 'COLLECTED')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        // Réservations à récupérer aujourd'hui
        $today = new \DateTimeImmutable();
        $tomorrow = $today->modify('+1 day');
        
        $reservationsToday = $reservationRepository->createQueryBuilder('r')
            ->where('r.status = :status')
            ->andWhere('r.expiresAt >= :today')
            ->andWhere('r.expiresAt < :tomorrow')
            ->setParameter('status', 'ACTIVE')
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
        ]);
    }
}
