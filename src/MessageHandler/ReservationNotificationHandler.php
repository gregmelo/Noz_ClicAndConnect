<?php

namespace App\MessageHandler;

use App\Message\ReservationNotificationMessage;
use App\Repository\ReservationRepository;
use App\Repository\UserRepository;
use App\Service\EmailNotificationService;
use App\Service\PushNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsMessageHandler]
class ReservationNotificationHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ReservationRepository $reservationRepository,
        private UserRepository $userRepository,
        private EmailNotificationService $emailService,
        private PushNotificationService $pushService,
        private UrlGeneratorInterface $urlGenerator,
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(ReservationNotificationMessage $message)
    {
        $reservation = $this->reservationRepository->find($message->getReservationId());

        if (!$reservation) {
            $this->logger->error('Reservation not found in ReservationNotificationHandler: ' . $message->getReservationId());
            return;
        }

        $user = $reservation->getUser();

        // 1. Send confirmation email to client
        try {
            $this->emailService->sendReservationConfirmation($reservation);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send reservation confirmation email: ' . $e->getMessage());
        }

        // 2. Send push notification to client
        try {
            $this->pushService->sendToUser(
                $user,
                '✅ Réservation confirmée',
                'Votre réservation ' . $reservation->getReference() . ' a bien été enregistrée.',
                $this->urlGenerator->generate('app_my_reservations', [], UrlGeneratorInterface::ABSOLUTE_URL)
            );
        } catch (\Exception $e) {
            $this->logger->error('Push notification failed for client ' . $user->getId() . ': ' . $e->getMessage());
        }

        // 3. Notify Employees
        try {
            $employees = $this->userRepository->findEmployees();
            foreach ($employees as $employee) {
                $this->pushService->sendToUser(
                    $employee,
                    '🔔 Nouvelle Réservation !',
                    'Une nouvelle réservation (' . $reservation->getReference() . ') vient d\'être effectuée.',
                    $this->urlGenerator->generate('app_employee_reservations', [], UrlGeneratorInterface::ABSOLUTE_URL)
                );
            }
            
            // Flush all push notifications (client + employees)
            $this->pushService->flush();
        } catch (\Exception $e) {
            $this->logger->error('Employee push notification failed for reservation ' . $reservation->getReference() . ': ' . $e->getMessage());
        }
    }
}
