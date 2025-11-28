<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class ActivityLogger
{
    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    public function logUserAction(UserInterface $user, string $action, array $context = []): void
    {
        $this->logger->info('User activity', [
            'user_id' => $user->getUserIdentifier(),
            'action' => $action,
            'context' => $context,
            'timestamp' => new \DateTimeImmutable(),
        ]);
    }

    public function logReservation(UserInterface $user, int $productId, int $quantity): void
    {
        $this->logUserAction($user, 'RESERVATION_CREATED', [
            'product_id' => $productId,
            'quantity' => $quantity,
        ]);
    }

    public function logReservationCancelled(UserInterface $user, int $reservationId): void
    {
        $this->logUserAction($user, 'RESERVATION_CANCELLED', [
            'reservation_id' => $reservationId,
        ]);
    }

    public function logReservationCollected(int $reservationId, string $employeeEmail): void
    {
        $this->logger->info('Reservation collected', [
            'reservation_id' => $reservationId,
            'employee' => $employeeEmail,
            'timestamp' => new \DateTimeImmutable(),
        ]);
    }

    public function logProductCreated(UserInterface $user, int $productId, string $productName): void
    {
        $this->logUserAction($user, 'PRODUCT_CREATED', [
            'product_id' => $productId,
            'product_name' => $productName,
        ]);
    }

    public function logProductUpdated(UserInterface $user, int $productId, string $productName): void
    {
        $this->logUserAction($user, 'PRODUCT_UPDATED', [
            'product_id' => $productId,
            'product_name' => $productName,
        ]);
    }

    public function logProductDeleted(UserInterface $user, int $productId): void
    {
        $this->logUserAction($user, 'PRODUCT_DELETED', [
            'product_id' => $productId,
        ]);
    }

    public function logLogin(string $userEmail): void
    {
        $this->logger->info('User login', [
            'user_email' => $userEmail,
            'timestamp' => new \DateTimeImmutable(),
        ]);
    }

    public function logFailedLogin(string $userEmail): void
    {
        $this->logger->warning('Failed login attempt', [
            'user_email' => $userEmail,
            'timestamp' => new \DateTimeImmutable(),
        ]);
    }
}
