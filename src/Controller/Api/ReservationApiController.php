<?php

namespace App\Controller\Api;

use App\Repository\ReservationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/reservations')]
#[IsGranted('ROLE_WARRIOR_JUNIOR')]
class ReservationApiController extends AbstractController
{
    #[Route('/count-new', name: 'api_reservations_count_new', methods: ['GET'])]
    public function countNew(ReservationRepository $reservationRepository): JsonResponse
    {
        // Count reservations with status 'ACTIVE' (New/To be processed only) AND NOT EXPIRED
        $count = $reservationRepository->createQueryBuilder('r')
            ->select('count(r.id)')
            ->where('r.status = :status')
            ->andWhere('r.expiresAt > :now')
            ->setParameter('status', 'ACTIVE')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getSingleScalarResult();

        return $this->json([
            'count' => $count,
        ]);
    }
}
