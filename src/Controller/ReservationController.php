<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Entity\ReservationItem;
use App\Entity\User;
use App\Repository\ReservationRepository;
use App\Service\ActivityLogger;
use App\Service\CartService;
use App\Service\EmailNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/reservation')]
#[IsGranted('ROLE_CLIENT')]
class ReservationController extends AbstractController
{
    public function __construct(
        private ActivityLogger $activityLogger,
        private EmailNotificationService $emailService,
        private CartService $cartService
    ) {
    }

    #[Route('/my-reservations', name: 'app_my_reservations')]
    public function myReservations(Request $request, ReservationRepository $reservationRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 10;
        $offset = ($page - 1) * $limit;

        $allReservations = $reservationRepository->findBy(['user' => $user], ['reservedAt' => 'DESC']);
        $totalReservations = count($allReservations);
        $totalPages = ceil($totalReservations / $limit);
        $reservations = array_slice($allReservations, $offset, $limit);

        return $this->render('reservation/my_reservations.html.twig', [
            'reservations' => $reservations,
            'currentPage' => $page,
            'totalPages' => $totalPages,
        ]);
    }

    #[Route('/validate', name: 'app_reservation_validate', methods: ['POST'])]
    public function validate(Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Check if user is banned
        if ($user->getBanExpiresAt() && $user->getBanExpiresAt() > new \DateTimeImmutable()) {
            $this->addFlash('danger', 'Vous êtes banni jusqu\'au ' . $user->getBanExpiresAt()->format('d/m/Y H:i'));
            return $this->redirectToRoute('app_banned');
        }

        $cartItems = $this->cartService->getFullCart();

        if (empty($cartItems)) {
            $this->addFlash('warning', 'Votre panier est vide.');
            return $this->redirectToRoute('app_cart_index');
        }

        // Check reservation limit (max 5 active reservations)
        // Note: Logic might need adjustment. Is it 5 active ORDERS or 5 active ITEMS?
        // Assuming 5 active ORDERS for now as per previous logic structure, but maybe we should limit items?
        // Let's stick to 5 active orders to prevent spamming.
        $activeReservationsCount = $entityManager->getRepository(Reservation::class)->count([
            'user' => $user,
            'status' => 'ACTIVE'
        ]);

        if ($activeReservationsCount >= 5) {
            $this->addFlash('danger', 'Vous avez atteint la limite de 5 réservations actives. Veuillez récupérer vos articles avant de réserver à nouveau.');
            return $this->redirectToRoute('app_cart_index');
        }

        // Create Reservation Header
        $reservation = new Reservation();
        $reservation->setUser($user);
        $reservation->setExpiresAt((new \DateTimeImmutable())->modify('+48 hours'));
        $reservation->setComment($request->request->get('comment'));
        
        // Generate Reference
        $date = (new \DateTime())->format('Ymd');
        $uniqId = strtoupper(substr(uniqid(), -4));
        $reservation->setReference(sprintf('RES-%s-%s', $date, $uniqId));

        $entityManager->persist($reservation);

        // Process Items
        foreach ($cartItems as $cartItem) {
            $product = $cartItem['product'];
            $quantity = $cartItem['quantity'];

            if ($product->getStock() < $quantity) {
                $this->addFlash('danger', 'Stock insuffisant pour le produit : ' . $product->getName());
                return $this->redirectToRoute('app_cart_index');
            }

            // Decrement stock
            $product->setStock($product->getStock() - $quantity);
            $entityManager->persist($product);

            // Create Reservation Item
            $item = new ReservationItem();
            $item->setReservation($reservation);
            $item->setProduct($product);
            $item->setQuantity($quantity);
            $entityManager->persist($item);
        }

        $entityManager->flush();

        // Log activity (using first product ID as reference or 0 for bulk)
        // Ideally update logger to handle bulk or just log "Reservation created"
        $this->activityLogger->logReservation($user, 0, count($cartItems)); // 0 = Bulk/Cart

        // Send confirmation email
        // We need to update email service to handle the new reservation structure
        // For now, passing the reservation object is enough if we update the template
        $this->emailService->sendReservationConfirmation($reservation);

        // Clear cart
        $this->cartService->clear();

        $this->addFlash('success', 'Réservation validée avec succès ! Référence : ' . $reservation->getReference());

        return $this->redirectToRoute('app_my_reservations');
    }

    #[Route('/cancel/{id}', name: 'app_reservation_cancel', methods: ['POST'])]
    public function cancel(Reservation $reservation, EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($reservation->getUser() !== $user) {
            throw $this->createAccessDeniedException();
        }

        // Allow cancellation for ACTIVE or READY
        if (!in_array($reservation->getStatus(), ['ACTIVE', 'READY'])) {
            $this->addFlash('danger', 'Cette réservation ne peut plus être annulée.');
            return $this->redirectToRoute('app_my_reservations');
        }

        // Restore stock for all items
        foreach ($reservation->getReservationItems() as $item) {
            $product = $item->getProduct();
            $product->setStock($product->getStock() + $item->getQuantity());
            $entityManager->persist($product);
        }

        $reservation->setStatus('CANCELLED');
        $entityManager->flush();

        // Log activity
        $this->activityLogger->logReservationCancelled($user, $reservation->getId());

        $this->addFlash('success', 'Réservation annulée. Le magasin a été notifié.');

        return $this->redirectToRoute('app_my_reservations');
    }

    #[Route('/ready/{id}', name: 'app_reservation_ready', methods: ['POST'])]
    #[IsGranted('ROLE_EMPLOYEE')]
    public function markAsReady(Reservation $reservation, EntityManagerInterface $entityManager): Response
    {
        if ($reservation->getStatus() !== 'ACTIVE') {
            $this->addFlash('danger', 'Cette réservation ne peut pas être marquée comme prête.');
            return $this->redirectToRoute('app_employee_reservations');
        }

        $reservation->setStatus('READY');
        $entityManager->flush();

        // Send email
        $this->emailService->sendReadyNotification($reservation);

        $this->addFlash('success', 'Réservation marquée comme prête. Le client a été notifié.');

        return $this->redirectToRoute('app_employee_reservations');
    }

    #[Route('/collect/{id}', name: 'app_reservation_collect', methods: ['POST'])]
    #[IsGranted('ROLE_EMPLOYEE')]
    public function markAsCollected(Reservation $reservation, EntityManagerInterface $entityManager): Response
    {
        // Allow collection from ACTIVE or READY
        if (!in_array($reservation->getStatus(), ['ACTIVE', 'READY'])) {
            $this->addFlash('danger', 'Cette réservation ne peut pas être marquée comme récupérée.');
            return $this->redirectToRoute('app_employee_reservations');
        }

        $reservation->setStatus('COLLECTED');
        $entityManager->flush();

        // Log activity
        $this->activityLogger->logReservationCollected($reservation->getId(), $this->getUser()->getUserIdentifier());

        $this->addFlash('success', 'Réservation marquée comme récupérée.');

        return $this->redirectToRoute('app_employee_reservations');
    }

    #[Route('/employee/reservations', name: 'app_employee_reservations')]
    #[IsGranted('ROLE_EMPLOYEE')]
    public function employeeReservations(ReservationRepository $reservationRepository): Response
    {
        $reservations = $reservationRepository->createQueryBuilder('r')
            ->where('r.status IN (:statuses)')
            ->setParameter('statuses', ['ACTIVE', 'READY'])
            ->orderBy('r.reservedAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('reservation/employee_reservations.html.twig', [
            'reservations' => $reservations,
        ]);
    }
}
