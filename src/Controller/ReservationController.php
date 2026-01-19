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

/**
 * ReservationController
 * 
 * Manages the complete lifecycle of customer reservations.
 * Accessible to ROLE_CLIENT for their own reservations, and ROLE_EMPLOYEE
 * for administrative actions (marking as ready, collected, etc.).
 */
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

    /**
     * Validate the current cart and create a reservation
     * Performs stock checks, CSRF validation, and reservation limit checks.
     *
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    #[Route('/validate', name: 'app_reservation_validate', methods: ['POST'])]
    public function validate(Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // CSRF Protection
        if (!$this->isCsrfTokenValid('reservation_validate', $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide. Veuillez réessayer.');
            return $this->redirectToRoute('app_cart_index');
        }

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
            $productId = $cartItem['product']->getId();
            // Fetch product with Pessimistic Write Lock (LockMode::PESSIMISTIC_WRITE = 4)
            /** @var \App\Entity\Product $product */
            $product = $entityManager->find(\App\Entity\Product::class, $productId, 4);

            if (!$product) {
                continue;
            }

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
            $item->setPrice($product->getPrice());
            $entityManager->persist($item);
            
            // Add to collection so it's available for the email immediately
            $reservation->addReservationItem($item);
        }

        $entityManager->flush();

        // Log activity (using first product ID as reference or 0 for bulk)
        // Ideally update logger to handle bulk or just log "Reservation created"
        $this->activityLogger->logReservation($user, $reservation->getReference(), count($cartItems));

        // Send confirmation email
        // We need to update email service to handle the new reservation structure
        // For now, passing the reservation object is enough if we update the template
        $this->emailService->sendReservationConfirmation($reservation);

        // Clear cart
        $this->cartService->clear();

        $this->addFlash('success', 'Réservation validée avec succès ! Référence : ' . $reservation->getReference());

        return $this->redirectToRoute('app_my_reservations');
    }

    /**
     * Cancel a reservation
     * Restores stock and applies strike/ban logic if cancelled after expiration.
     *
     * @param Reservation $reservation
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    #[Route('/cancel/{id}', name: 'app_reservation_cancel', methods: ['POST'])]
    public function cancel(Reservation $reservation, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('cancel_reservation'.$reservation->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('app_my_reservations');
        }

        /** @var User $user */
        $user = $this->getUser();

        // Allow owner OR employee
        if ($reservation->getUser() !== $user && !$this->isGranted('ROLE_EMPLOYEE')) {
            throw $this->createAccessDeniedException();
        }

        // Allow cancellation for ACTIVE or READY
        if (!in_array($reservation->getStatus(), ['ACTIVE', 'READY'])) {
            $this->addFlash('danger', 'Cette réservation ne peut plus être annulée.');
            
            if ($this->isGranted('ROLE_EMPLOYEE')) {
                return $this->redirectToRoute('app_employee_reservations');
            }
            return $this->redirectToRoute('app_my_reservations');
        }

        // Restore stock for all items
        foreach ($reservation->getReservationItems() as $item) {
            $product = $item->getProduct();
            $product->setStock($product->getStock() + $item->getQuantity());
            $entityManager->persist($product);
        }

        // Check if expired to apply Strike logic
        if ($reservation->isExpired()) {
            if ($reservation->getStatus() === 'READY') {
                $reservation->setStatus('EXPIRED'); 
                
                // Add Strike to User
                $owner = $reservation->getUser();
                $owner->setStrikes($owner->getStrikes() + 1);
                
                // Ban logic (e.g. >= 3 strikes)
                if ($owner->getStrikes() >= 3) {
                     $owner->setBanExpiresAt((new \DateTimeImmutable())->modify('+30 days'));
                     $this->addFlash('warning', 'Utilisateur banni pour 30 jours (3 strikes).');
                }
                $entityManager->persist($owner);
                
                $this->addFlash('success', 'Réservation marquée comme EXPIRÉE. Stock rétabli et Strike ajouté.');
            } else {
                // Expired but was never READY (store didn't prepare it in time)
                $reservation->setStatus('CANCELLED');
                $this->addFlash('info', 'Réservation expirée sans avoir été préparée par le magasin. Stock rétabli sans pénalité pour le client.');
            }
        } else {
            $reservation->setStatus('CANCELLED');
            $this->addFlash('success', 'Réservation annulée et articles remis en stock.');
        }

        $entityManager->flush();

        // Log activity
        $this->activityLogger->logReservationCancelled($user, $reservation->getReference());

        if ($this->isGranted('ROLE_EMPLOYEE')) {
            return $this->redirectToRoute('app_employee_reservations');
        }

        return $this->redirectToRoute('app_my_reservations');
    }

    /**
     * Mark a reservation as ready for collection
     * Updates expiration date according to morning/afternoon logic.
     *
     * @param Reservation $reservation
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    #[Route('/ready/{id}', name: 'app_reservation_ready', methods: ['POST'])]
    public function markAsReady(Reservation $reservation, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('ready_reservation'.$reservation->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('app_employee_reservations');
        }

        if ($reservation->getStatus() !== 'ACTIVE') {
            $this->addFlash('danger', 'Cette réservation ne peut pas être marquée comme prête.');
            return $this->redirectToRoute('app_employee_reservations');
        }

        $reservation->setStatus('READY');

        // Logic d'expiration : 
        // - Avant midi -> 19h30 le jour même
        // - Après midi -> 19h30 le lendemain (sauf si dimanche -> lundi)
        $now = new \DateTimeImmutable();
        $hour = (int) $now->format('G');

        if ($hour < 12) {
            // Matin : 19h30 le jour même
            $expiresAt = $now->setTime(19, 30);
        } else {
            // Après-midi : 19h30 le lendemain
            $expiresAt = $now->modify('+1 day')->setTime(19, 30);
            
            // Si le lendemain est Dimanche (7), on reporte à Lundi
            if ($expiresAt->format('N') == 7) {
                $expiresAt = $expiresAt->modify('+1 day');
            }
        }
        
        $reservation->setExpiresAt($expiresAt);

        $entityManager->flush();

        // Send email (will use the new expiresAt date)
        $this->emailService->sendReadyNotification($reservation);

        $this->addFlash('success', 'Réservation prête. Le client a jusqu\'au ' . $expiresAt->format('d/m H:i') . ' pour la récupérer.');

        return $this->redirectToRoute('app_employee_reservations');
    }

    /**
     * Mark a reservation as collected by the customer
     *
     * @param Reservation $reservation
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    #[Route('/collect/{id}', name: 'app_reservation_collect', methods: ['POST'])]
    public function markAsCollected(Reservation $reservation, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('collect_reservation'.$reservation->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('app_employee_reservations');
        }

        // Allow collection from ACTIVE or READY
        if (!in_array($reservation->getStatus(), ['ACTIVE', 'READY'])) {
            $this->addFlash('danger', 'Cette réservation ne peut pas être marquée comme récupérée.');
            return $this->redirectToRoute('app_employee_reservations');
        }

        $reservation->setStatus('COLLECTED');

        // Update persistent stats for each products creator
        foreach ($reservation->getReservationItems() as $item) {
            $product = $item->getProduct();
            /** @var User|null $creator */
            $creator = $product->getCreatedBy();

            if ($creator) {
                $creator->addCumulativeRevenue((float) ($item->getQuantity() * $item->getPrice()));
                $creator->addCumulativeSoldItems($item->getQuantity());
                $entityManager->persist($creator);
            }
        }

        // --- STRIKE REHABILITATION LOGIC ---
        /** @var User $client */
        $client = $reservation->getUser();
        $client->setSuccessfulCollectionsCount($client->getSuccessfulCollectionsCount() + 1);

        if ($client->getSuccessfulCollectionsCount() >= 3) {
            // Remove 1 strike if the user has any
            if ($client->getStrikes() > 0) {
                $client->setStrikes($client->getStrikes() - 1);
                $this->addFlash('info', 'Réhabilitation : Un "Strike" a été retiré de votre compte pour votre bonne conduite !');
            }
            // Reset the counter
            $client->setSuccessfulCollectionsCount(0);
        }
        $entityManager->persist($client);
        // ------------------------------------

        $entityManager->flush();

        // Log activity
        $this->activityLogger->logReservationCollected($reservation->getReference(), $this->getUser()->getUserIdentifier());

        $this->addFlash('success', 'Réservation marquée comme récupérée.');

        return $this->redirectToRoute('app_employee_reservations');
    }

    /**
     * Dashboard for employees to manage all reservations
     *
     * @param ReservationRepository $reservationRepository
     * @param \App\Repository\GlobalStatRepository $globalStatRepository
     * @return Response
     */
    #[Route('/employee/reservations', name: 'app_employee_reservations')]
    public function employeeReservations(ReservationRepository $reservationRepository, \App\Repository\GlobalStatRepository $globalStatRepository): Response
    {
        $now = new \DateTimeImmutable();

        // 1. Nouvelles (ACTIVE et non expirées)
        $newReservations = $reservationRepository->createQueryBuilder('r')
            ->where('r.status = :status')
            ->andWhere('r.expiresAt > :now')
            ->setParameter('status', 'ACTIVE')
            ->setParameter('now', $now)
            ->orderBy('r.reservedAt', 'DESC')
            ->getQuery()
            ->getResult();

        // 2. À récupérer (READY et non expirées)
        $readyReservations = $reservationRepository->createQueryBuilder('r')
            ->where('r.status = :status')
            ->andWhere('r.expiresAt > :now')
            ->setParameter('status', 'READY')
            ->setParameter('now', $now)
            ->orderBy('r.reservedAt', 'ASC') // Les plus anciennes prêtes en premier (urgence)
            ->getQuery()
            ->getResult();

        // 3. Expirées (CANCELED, EXPIRED ou date passée et non récupérées)
        $expiredReservations = $reservationRepository->createQueryBuilder('r')
            ->where('r.status IN (:done_statuses)')
            ->orWhere('r.status IN (:active_ready) AND r.expiresAt <= :now')
            ->setParameter('done_statuses', ['CANCELLED', 'EXPIRED'])
            ->setParameter('active_ready', ['ACTIVE', 'READY'])
            ->setParameter('now', $now)
            ->orderBy('r.expiresAt', 'DESC')
            ->setMaxResults(20) // Limit display
            ->getQuery()
            ->getResult();

        // 4. Traitées (COLLECTED)
        $collectedReservations = $reservationRepository->createQueryBuilder('r')
            ->where('r.status = :status')
            ->setParameter('status', 'COLLECTED')
            ->orderBy('r.reservedAt', 'DESC')
            ->setMaxResults(20) // Limit display
            ->getQuery()
            ->getResult();

        return $this->render('reservation/employee_reservations.html.twig', [
            'newReservations' => $newReservations,
            'readyReservations' => $readyReservations,
            'expiredReservations' => $expiredReservations,
            'collectedReservations' => $collectedReservations,
        ]);
    }

    /**
     * Force cleanup of expired reservations (Admin only)
     *
     * @param \Symfony\Component\HttpKernel\KernelInterface $kernel
     * @return Response
     */
    #[Route('/employee/cleanup', name: 'app_employee_cleanup')]
    public function cleanup(\Symfony\Component\HttpKernel\KernelInterface $kernel): Response
    {
        // Security Check: Admin, Super Admin, or Dev
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_SUPER_ADMIN') && !$this->isGranted('ROLE_DEV')) {
            throw $this->createAccessDeniedException('Accès réservé aux administrateurs.');
        }

        $application = new \Symfony\Bundle\FrameworkBundle\Console\Application($kernel);
        $application->setAutoExit(false);

        $input = new \Symfony\Component\Console\Input\ArrayInput([
            'command' => 'app:cleanup-reservations',
        ]);

        $output = new \Symfony\Component\Console\Output\BufferedOutput();
        $application->run($input, $output);

        $content = $output->fetch();

        // Simple check for success string or just flash the output
        $this->addFlash('info', 'Nettoyage terminé : ' . $content);

        return $this->redirectToRoute('app_employee_reservations');
    }

    /**
     * Generate a preparation list for selected reservations (Employee)
     *
     * @param Request $request
     * @param ReservationRepository $reservationRepository
     * @return Response
     */
    #[Route('/employee/preparation', name: 'app_reservation_preparation', methods: ['POST'])]
    public function preparationList(Request $request, ReservationRepository $reservationRepository): Response
    {
        $reservationIds = $request->request->all('reservation_ids');
        
        if (empty($reservationIds)) {
            $this->addFlash('warning', 'Veuillez sélectionner au moins une réservation.');
            return $this->redirectToRoute('app_employee_reservations');
        }

        $reservations = $reservationRepository->findBy(['id' => $reservationIds]);
        
        // Aggregate items
        $aggregatedItems = [];
        foreach ($reservations as $reservation) {
            foreach ($reservation->getReservationItems() as $item) {
                $productId = $item->getProduct()->getId();
                if (!isset($aggregatedItems[$productId])) {
                    $aggregatedItems[$productId] = [
                        'product' => $item->getProduct(),
                        'quantity' => 0,
                        'references' => []
                    ];
                }
                $aggregatedItems[$productId]['quantity'] += $item->getQuantity();
                $aggregatedItems[$productId]['references'][] = $reservation->getReference();
            }
        }

        // Sort by Category name
        usort($aggregatedItems, function($a, $b) {
            $catA = $a['product']->getCategory() ? $a['product']->getCategory()->getName() : 'Z_SANS_CATEGORIE';
            $catB = $b['product']->getCategory() ? $b['product']->getCategory()->getName() : 'Z_SANS_CATEGORIE';
            return strcmp($catA, $catB);
        });

        return $this->render('reservation/preparation_list.html.twig', [
            'items' => $aggregatedItems,
            'reservations' => $reservations,
            'reservationIds' => $reservationIds
        ]);
    }

    /**
     * Mark multiple reservations as ready in bulk
     *
     * @param Request $request
     * @param ReservationRepository $reservationRepository
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    #[Route('/employee/batch-ready', name: 'app_reservation_batch_ready', methods: ['POST'])]
    public function batchReady(Request $request, ReservationRepository $reservationRepository, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('batch_ready_reservations', $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('app_employee_reservations');
        }

        $reservationIds = $request->request->all('reservation_ids');
        
        if (empty($reservationIds)) {
            $this->addFlash('warning', 'Aucune réservation à valider.');
            return $this->redirectToRoute('app_employee_reservations');
        }

        $reservations = $reservationRepository->findBy(['id' => $reservationIds]);
        $count = 0;

        foreach ($reservations as $reservation) {
            if ($reservation->getStatus() === 'ACTIVE') {
                $reservation->setStatus('READY');
                
                // Expiry logic (same as markAsReady)
                $now = new \DateTimeImmutable();
                $hour = (int) $now->format('G');
                if ($hour < 12) {
                    $expiresAt = $now->setTime(19, 30);
                } else {
                    $expiresAt = $now->modify('+1 day')->setTime(19, 30);
                    if ($expiresAt->format('N') == 7) {
                        $expiresAt = $expiresAt->modify('+1 day');
                    }
                }
                $reservation->setExpiresAt($expiresAt);
                
                // Send email
                $this->emailService->sendReadyNotification($reservation);
                $count++;
            }
        }

        $entityManager->flush();

        // Audit Log
        if ($count > 0) {
            $this->activityLogger->logUserAction($this->getUser(), 'RESERVATIONS_BATCH_READY', [
                'count' => $count,
                'reservation_ids' => $reservationIds
            ]);
        }

        $this->addFlash('success', sprintf('%d réservations ont été marquées comme PRÊTES.', $count));
        
        return $this->redirectToRoute('app_employee_reservations');
    }
}
