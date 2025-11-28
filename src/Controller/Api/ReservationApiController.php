<?php

namespace App\Controller\Api;

use App\Repository\ReservationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/reservations')]
#[IsGranted('ROLE_EMPLOYEE')]
class ReservationApiController extends AbstractController
{
    #[Route('/count-new', name: 'api_reservations_count_new', methods: ['GET'])]
    public function countNew(ReservationRepository $reservationRepository): JsonResponse
    {
        // Count reservations with status 'ACTIVE' (which we treat as 'New' for now)
        // Ideally we would have a 'viewed' flag or a 'created_at' check, 
        // but for now, let's just count ACTIVE ones as "to be processed".
        // Or better, count ACTIVE ones.
        $count = $reservationRepository->count(['status' => 'ACTIVE']);

        return $this->json([
            'count' => $count,
        ]);
    }
}
