<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Entity\ReservationItem;
use App\Entity\User;
use App\Message\ReservationNotificationMessage;
use App\Repository\ReservationRepository;
use App\Repository\UserRepository;
use App\Service\ActivityLogger;
use Psr\Log\LoggerInterface;
use App\Service\CartService;
use App\Service\EmailNotificationService;
use App\Service\PushNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Contrôleur gérant le cycle de vie complet des réservations.
 * 
 * Ce contrôleur permet aux clients de valider leurs paniers, de suivre leurs réservations,
 * et aux employés de gérer le flux logistique (préparation, mise à disposition, retrait).
 * Il intègre également des fonctionnalités de temps réel via Mercure et des notifications Push.
 */
#[Route('/reservation')]
#[IsGranted('ROLE_CLIENT')]
class ReservationController extends AbstractController
{
    /**
     * Injection des dépendances via le constructeur.
     */
    public function __construct(
        private ActivityLogger $activityLogger,
        private EmailNotificationService $emailService,
        private CartService $cartService,
        private PushNotificationService $pushService,
        private UserRepository $userRepository,
        private LoggerInterface $logger,
        private MessageBusInterface $messageBus,
        private \Symfony\Component\RateLimiter\RateLimiterFactory $reservationLimiter,
        private HubInterface $hub,
        private ReservationRepository $reservationRepo
    ) {
    }

    /**
     * Affiche la liste des réservations du client connecté.
     * Gère la pagination pour optimiser les performances.
     */
    #[Route('/my-reservations', name: 'app_my_reservations')]
    public function myReservations(Request $request, ReservationRepository $reservationRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Paramètres de pagination
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 10;
        $offset = ($page - 1) * $limit;

        // Récupération des réservations de l'utilisateur
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
     * Valide le panier actuel et crée (ou met à jour) une réservation.
     * 
     * Actions réalisées :
     * 1. Vérification du Rate Limiting et CSRF.
     * 2. Contrôle du statut de bannissement du client.
     * 3. Groupement automatique avec une réservation ACTIVE existante si possible.
     * 4. Vérification stricte des stocks avec verrouillage pessimiste (PESSIMISTIC_WRITE).
     * 5. Déclenchement des notifications asynchrones et mise à jour temps réel Mercure.
     */
    #[Route('/validate', name: 'app_reservation_validate', methods: ['POST'])]
    public function validate(Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Protection contre le spam (Rate Limiting)
        $limiter = $this->reservationLimiter->create($request->getClientIp());
        if (false === $limiter->consume(1)->isAccepted()) {
            $this->addFlash('danger', 'Trop de tentatives de réservation. Veuillez patienter une minute.');
            return $this->redirectToRoute('app_cart_index');
        }

        // Sécurité CSRF
        if (!$this->isCsrfTokenValid('reservation_validate', $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide. Veuillez réessayer.');
            return $this->redirectToRoute('app_cart_index');
        }

        // Vérification du bannissement (Strikes)
        if ($user->getBanExpiresAt() && $user->getBanExpiresAt() > new \DateTimeImmutable()) {
            $this->addFlash('danger', 'Vous êtes banni jusqu\'au ' . $user->getBanExpiresAt()->format('d/m/Y H:i'));
            return $this->redirectToRoute('app_banned');
        }

        $cartItems = $this->cartService->getFullCart();
        if (empty($cartItems)) {
            $this->addFlash('warning', 'Votre panier est vide.');
            return $this->redirectToRoute('app_home');
        }

        // Début de la transaction pour le verrouillage pessimiste des stocks
        $entityManager->beginTransaction();
        try {
            // Tentative de groupement avec une réservation ACTIVE non expirée
            /** @var Reservation|null $existingReservation */
            $existingReservation = $entityManager->getRepository(Reservation::class)->findOneBy([
                'user' => $user,
                'status' => 'ACTIVE'
            ], ['reservedAt' => 'DESC']);

            if ($existingReservation && $existingReservation->isExpired()) {
                $existingReservation = null;
            }

            if ($existingReservation) {
                $reservation = $existingReservation;
                // Une réservation ACTIVE (en attente) n'expire plus automatiquement
                $reservation->setExpiresAt((new \DateTimeImmutable())->modify('+10 years'));

                // Ajout du nouveau commentaire si présent
                $newComment = $request->request->get('comment');
                if ($newComment) {
                    $currentComment = $reservation->getComment();
                    $reservation->setComment($currentComment ? $currentComment . " | " . $newComment : $newComment);
                }
            } else {
                // Limite de 5 réservations actives par client
                $activeReservationsCount = $entityManager->getRepository(Reservation::class)->count([
                    'user' => $user,
                    'status' => 'ACTIVE'
                ]);

                if ($activeReservationsCount >= 5) {
                    $this->addFlash('danger', 'Vous avez atteint la limite de 5 réservations actives. Veuillez récupérer vos articles avant de réserver à nouveau.');
                    $entityManager->rollback();
                    return $this->redirectToRoute('app_cart_index');
                }

                // Création d'une nouvelle réservation
                $reservation = new Reservation();
                $reservation->setUser($user);
                $reservation->setExpiresAt((new \DateTimeImmutable())->modify('+10 years'));
                $reservation->setComment($request->request->get('comment'));

                // Génération d'une référence unique : RES-YYYYMMDD-XXXX
                $date = (new \DateTime())->format('Ymd');
                $uniqId = strtoupper(substr(uniqid(), -4));
                $reservation->setReference(sprintf('RES-%s-%s', $date, $uniqId));

                $entityManager->persist($reservation);
            }

            // Traitement des produits du panier
            foreach ($cartItems as $cartItem) {
                $productId = $cartItem['product']->getId();

                // Verrouillage de la ligne SQL pour éviter les race conditions sur le stock
                /** @var \App\Entity\Product $product */
                $product = $entityManager->find(\App\Entity\Product::class, $productId, \Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE);

                if (!$product)
                    continue;

                $quantity = $cartItem['quantity'];

                // Validation de la disponibilité
                if (!$product->isLive()) {
                    $this->addFlash('danger', 'Le produit ' . $product->getName() . ' n\'est plus disponible pour le live.');
                    $entityManager->rollback();
                    return $this->redirectToRoute('app_cart_index');
                }

                if ($product->getStock() < $quantity) {
                    $this->addFlash('danger', 'Stock insuffisant pour le produit : ' . $product->getName());
                    $entityManager->rollback();
                    return $this->redirectToRoute('app_cart_index');
                }

                // Mise à jour du stock
                $product->setStock($product->getStock() - $quantity);
                $entityManager->persist($product);

                // Ajout ou mise à jour de l'item dans la réservation
                $existingItem = null;
                foreach ($reservation->getReservationItems() as $resItem) {
                    if ($resItem->getProduct()->getId() === $product->getId()) {
                        $existingItem = $resItem;
                        break;
                    }
                }

                if ($existingItem) {
                    $existingItem->setQuantity($existingItem->getQuantity() + $quantity);
                    $entityManager->persist($existingItem);
                } else {
                    $item = new ReservationItem();
                    $item->setReservation($reservation);
                    $item->setProduct($product);
                    $item->setQuantity($quantity);
                    $item->setPrice($product->getPrice());
                    $entityManager->persist($item);
                    $reservation->addReservationItem($item);
                }
            }

            $entityManager->flush();
            $entityManager->commit();

        } catch (\Exception $e) {
            if ($entityManager->getConnection()->isTransactionActive()) {
                $entityManager->rollback();
            }
            $this->addFlash('danger', 'Une erreur est survenue lors de la validation : ' . $e->getMessage());
            return $this->redirectToRoute('app_cart_index');
        }

        // Enregistrement de l'action dans les logs d'audit
        $this->activityLogger->logReservation($user, $reservation->getReference(), count($cartItems));

        // Envoi asynchrone des notifications (E-mail / Push via Messenger)
        $this->messageBus->dispatch(new ReservationNotificationMessage($reservation->getId()));

        // Vidage du panier et notifications temps réel
        $this->cartService->clear();
        $this->publishStatsUpdate();
        $this->publishPageRefresh();

        $this->addFlash('success', sprintf(
            'Réservation %s effectuée avec succès ! Référence : %s',
            $existingReservation ? 'mise à jour' : 'validée',
            $reservation->getReference()
        ));

        return $this->redirectToRoute('app_my_reservations');
    }

    /**
     * Annule une réservation et remet les produits en stock.
     * Gère les pénalités (Strikes) si l'annulation survient après expiration d'une commande PRÊTE.
     */
    #[Route('/cancel/{id}', name: 'app_reservation_cancel', methods: ['POST'])]
    public function cancel(Reservation $reservation, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('cancel_reservation' . $reservation->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('app_my_reservations');
        }

        /** @var User $user */
        $user = $this->getUser();

        // Vérification des droits : Propriétaire ou Employé
        if ($reservation->getUser() !== $user && !$this->isGranted('ROLE_WARRIOR_JUNIOR')) {
            throw $this->createAccessDeniedException();
        }

        if (!in_array($reservation->getStatus(), ['ACTIVE', 'READY'])) {
            $this->addFlash('danger', 'Cette réservation ne peut plus être annulée.');
            return $this->redirectToRoute($this->isGranted('ROLE_WARRIOR_JUNIOR') ? 'app_employee_reservations' : 'app_my_reservations');
        }

        // Restauration des stocks
        foreach ($reservation->getReservationItems() as $item) {
            $product = $item->getProduct();
            $product->setStock($product->getStock() + $item->getQuantity());
            $entityManager->persist($product);
        }

        // Si la réservation était prête et a expiré : application d'un Strike
        if ($reservation->isExpired()) {
            if ($reservation->getStatus() === 'READY') {
                $reservation->setStatus('EXPIRED');
                $owner = $reservation->getUser();
                $owner->setStrikes($owner->getStrikes() + 1);

                // Bannissement automatique à partir de 3 strikes
                if ($owner->getStrikes() >= 3) {
                    $owner->setBanExpiresAt((new \DateTimeImmutable())->modify('+7 days'));
                    $this->addFlash('warning', 'Utilisateur banni pour 7 jours (3 strikes).');
                }
                $entityManager->persist($owner);
                $this->addFlash('success', 'Réservation marquée comme EXPIRÉE. Stock rétabli et Strike ajouté.');
            } else {
                $reservation->setStatus('CANCELLED');
                $this->addFlash('info', 'Réservation expirée et annulée. Stock rétabli sans pénalité.');
            }
        } else {
            $reservation->setStatus('CANCELLED');
            $this->addFlash('success', 'Réservation annulée et articles remis en stock.');
        }

        $entityManager->flush();
        $this->publishStatsUpdate();
        $this->publishPageRefresh();

        $this->activityLogger->logReservationCancelled($user, $reservation->getReference());

        return $this->redirectToRoute($this->isGranted('ROLE_WARRIOR_JUNIOR') ? 'app_employee_reservations' : 'app_my_reservations');
    }

    /**
     * Marque une réservation comme "PRÊTE" pour le retrait.
     * Calcule automatiquement la nouvelle date d'expiration (Matin/Après-midi).
     */
    #[Route('/ready/{id}', name: 'app_reservation_ready', methods: ['POST'])]
    public function markAsReady(Reservation $reservation, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('ready_reservation' . $reservation->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('app_employee_reservations');
        }

        if ($reservation->getStatus() !== 'ACTIVE') {
            $this->addFlash('danger', 'Cette réservation ne peut pas être marquée comme prête.');
            return $this->redirectToRoute('app_employee_reservations');
        }

        $reservation->setStatus('READY');

        // Calcul de la date d'échéance : Demain 19h30
        $expiresAt = (new \DateTimeImmutable())->modify('+1 day')->setTime(19, 30);

        if ($expiresAt->format('N') == 7) { // Dimanche -> Report au Lundi 19h30
            $expiresAt = $expiresAt->modify('+1 day');
        }

        $reservation->setExpiresAt($expiresAt);
        $entityManager->flush();

        // Envoi des notifications de disponibilité
        $this->emailService->sendReadyNotification($reservation);

        try {
            $this->pushService->sendToUser(
                $reservation->getUser(),
                '📦 Votre commande est prête !',
                'Bonne nouvelle ! Votre réservation ' . $reservation->getReference() . ' est prête pour le retrait.',
                $this->generateUrl('app_my_reservations', [], UrlGeneratorInterface::ABSOLUTE_URL)
            );
            $this->pushService->flush();
        } catch (\Exception $e) {
            $this->logger->error('Erreur Push notification : ' . $e->getMessage());
        }

        $this->addFlash('success', 'Réservation prête. Le client a jusqu\'au ' . $expiresAt->format('d/m H:i') . ' pour la récupérer.');

        $this->publishStatsUpdate();
        $this->publishPageRefresh();

        return $this->redirectToRoute('app_employee_reservations');
    }

    /**
     * Marque la réservation comme récupérée par le client.
     * Met à jour les statistiques de vente et gère la réhabilitation des strikes.
     */
    #[Route('/collect/{id}', name: 'app_reservation_collect', methods: ['POST'])]
    public function markAsCollected(Reservation $reservation, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('collect_reservation' . $reservation->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('app_employee_reservations');
        }

        if (!in_array($reservation->getStatus(), ['ACTIVE', 'READY'])) {
            $this->addFlash('danger', 'Cette réservation ne peut pas être marquée comme récupérée.');
            return $this->redirectToRoute('app_employee_reservations');
        }

        $reservation->setStatus('COLLECTED');

        // Mise à jour des revenus cumulés pour les créateurs de produits
        foreach ($reservation->getReservationItems() as $item) {
            $product = $item->getProduct();
            $creator = $product->getCreatedBy();
            if ($creator) {
                $creator->addCumulativeRevenue((float) ($item->getQuantity() * $item->getPrice()));
                $creator->addCumulativeSoldItems($item->getQuantity());
                $entityManager->persist($creator);
            }
        }

        // Système de réhabilitation : Réduire les strikes si le client est exemplaire (3 retraits réussis)
        /** @var User $client */
        $client = $reservation->getUser();
        $client->setSuccessfulCollectionsCount($client->getSuccessfulCollectionsCount() + 1);

        if ($client->getSuccessfulCollectionsCount() >= 3) {
            if ($client->getStrikes() > 0) {
                $client->setStrikes($client->getStrikes() - 1);
                $this->addFlash('info', 'Réhabilitation : Un Strike a été retiré pour votre bonne conduite !');
            }
            $client->setSuccessfulCollectionsCount(0);
        }
        $entityManager->persist($client);

        $entityManager->flush();
        $this->publishStatsUpdate();
        $this->publishPageRefresh();

        $this->activityLogger->logReservationCollected($reservation->getReference(), $this->getUser()->getUserIdentifier());
        $this->addFlash('success', 'Réservation marquée comme récupérée.');

        return $this->redirectToRoute('app_employee_reservations');
    }

    /**
     * Tableau de bord employé : Affiche les réservations triées par importance logistique.
     */
    #[Route('/employee/reservations', name: 'app_employee_reservations')]
    public function employeeReservations(ReservationRepository $reservationRepository): Response
    {
        $now = new \DateTimeImmutable();

        // 1. Nouvelles demandes actives
        $newReservations = $reservationRepository->createQueryBuilder('r')
            ->where('r.status = :status AND r.expiresAt > :now')
            ->setParameter('status', 'ACTIVE')
            ->setParameter('now', $now)
            ->orderBy('r.reservedAt', 'DESC')
            ->getQuery()->getResult();

        // 2. Commandes prêtes en attente de retrait
        $readyReservations = $reservationRepository->createQueryBuilder('r')
            ->where('r.status = :status AND r.expiresAt > :now')
            ->setParameter('status', 'READY')
            ->setParameter('now', $now)
            ->orderBy('r.reservedAt', 'ASC')
            ->getQuery()->getResult();

        // 3. Commandes expirées (pour traitement des strikes)
        $expiredReservations = $reservationRepository->createQueryBuilder('r')
            ->where('r.status = :status_expired OR (r.status IN (:active_ready) AND r.expiresAt <= :now)')
            ->setParameter('status_expired', 'EXPIRED')
            ->setParameter('active_ready', ['ACTIVE', 'READY'])
            ->setParameter('now', $now)
            ->orderBy('r.expiresAt', 'DESC')
            ->setMaxResults(20)->getQuery()->getResult();

        // 4. Annulations manuelles du client
        $cancelledReservations = $reservationRepository->createQueryBuilder('r')
            ->where('r.status = :status_cancelled')
            ->setParameter('status_cancelled', 'CANCELLED')
            ->orderBy('r.reservedAt', 'DESC')
            ->setMaxResults(20)->getQuery()->getResult();

        // 5. Historique des commandes récupérées
        $collectedReservations = $reservationRepository->createQueryBuilder('r')
            ->where('r.status = :status')
            ->setParameter('status', 'COLLECTED')
            ->orderBy('r.reservedAt', 'DESC')
            ->setMaxResults(20)->getQuery()->getResult();

        return $this->render('reservation/employee_reservations.html.twig', [
            'newReservations' => $newReservations,
            'readyReservations' => $readyReservations,
            'expiredReservations' => $expiredReservations,
            'cancelledReservations' => $cancelledReservations,
            'collectedReservations' => $collectedReservations,
        ]);
    }

    /**
     * Génère une liste de préparation agrégée pour plusieurs réservations (Pick list).
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

        // Agrégation des produits par référence pour optimiser le ramassage en rayon
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

        // Tri par catégorie de produit pour faciliter le parcours en magasin
        usort($aggregatedItems, function ($a, $b) {
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
     * Valide un lot de réservations comme prêtes en une seule fois.
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
                $this->emailService->sendReadyNotification($reservation);

                try {
                    $this->pushService->sendToUser(
                        $reservation->getUser(),
                        '📦 Votre commande est prête !',
                        'Votre réservation ' . $reservation->getReference() . ' est prête pour le retrait.',
                        $this->generateUrl('app_my_reservations', [], UrlGeneratorInterface::ABSOLUTE_URL)
                    );
                } catch (\Exception $e) {
                }
                $count++;
            }
        }

        $entityManager->flush();
        $this->pushService->flush();
        $this->publishPageRefresh();

        $this->addFlash('success', sprintf('%d réservations ont été marquées comme PRÊTES.', $count));
        return $this->redirectToRoute('app_employee_reservations');
    }

    /**
     * Publie les compteurs de réservations mis à jour via Mercure.
     */
    private function publishStatsUpdate(): void
    {
        $count = (int) $this->reservationRepo->createQueryBuilder('r')
            ->select('count(r.id)')
            ->where('r.status = :status AND r.expiresAt > :now')
            ->setParameter('status', 'ACTIVE')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()->getSingleScalarResult();

        $this->hub->publish(new Update(
            'https://nozamberieu.fr/employee/stats',
            json_encode(['event' => 'reservation_count_updated', 'count' => $count])
        ));
    }

    /**
     * Signale aux clients de rafraîchir leurs listes de réservations.
     */
    private function publishPageRefresh(): void
    {
        $this->hub->publish(new Update(
            'res-updates',
            json_encode(['event' => 'reservation_updated', 'timestamp' => time()])
        ));
    }
    /**
     * Nettoie les anciennes réservations terminées (plus de 7 jours).
     */
    #[Route('/employee/cleanup', name: 'app_employee_cleanup', methods: ['GET'])]
    #[IsGranted('ROLE_WARRIOR')]
    public function cleanup(EntityManagerInterface $entityManager): Response
    {
        $limitDate = (new \DateTimeImmutable())->modify('-7 days');

        $count = $entityManager->createQueryBuilder()
            ->delete(Reservation::class, 'r')
            ->where('r.status IN (:statuses)')
            ->andWhere('r.reservedAt < :limitDate')
            ->setParameter('statuses', ['CANCELLED', 'COLLECTED', 'EXPIRED'])
            ->setParameter('limitDate', $limitDate)
            ->getQuery()
            ->execute();

        $this->addFlash('success', sprintf('%d anciennes réservations ont été définitivement supprimées.', $count));

        return $this->redirectToRoute('app_employee_reservations');
    }

    #[Route('/api/csrf-token/{intention}', name: 'app_csrf_token', methods: ['GET'])]
    public function getCsrfToken(string $intention): Response
    {
        return $this->json([
            'token' => $this->container->get('security.csrf.token_manager')
                ->getToken($intention)->getValue()
        ]);
    }
}
