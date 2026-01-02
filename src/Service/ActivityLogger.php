<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * ActivityLogger Service
 * 
 * Centralized service for recording user and system actions.
 * Logs are written to both the standard logger (files) and the database (ActivityLog entity).
 */
class ActivityLogger
{
    /**
     * @param LoggerInterface $logger standard PSR logger
     * @param \Doctrine\ORM\EntityManagerInterface $entityManager
     */
    public function __construct(
        private LoggerInterface $logger,
        private \Doctrine\ORM\EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Log a generic user action to both file and database.
     *
     * @param UserInterface $user
     * @param string $action Action name (e.g., 'RESERVATION_CREATED')
     * @param array $context Additional data for the log
     */
    public function logUserAction(UserInterface $user, string $action, array $context = []): void
    {
        $this->logToDatabase($user->getUserIdentifier(), $action, $context);
    }

    private function logToDatabase(?string $userEmail, string $action, array $context = []): void
    {
        // 1. Log to file (redundancy)
        $this->logger->info($action, [
            'user' => $userEmail,
            'context' => $context,
            'timestamp' => new \DateTimeImmutable(),
        ]);

        // 2. Persist to Database
        $log = new \App\Entity\ActivityLog();
        $log->setUserEmail($userEmail);
        $log->setAction($action);
        $log->setContext($context);
        
        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }

    /**
     * Log a new reservation event
     *
     * @param UserInterface $user
     * @param string $reference
     * @param int $quantity Total quantity of items in the reservation
     */
    public function logReservation(UserInterface $user, string $reference, int $quantity): void
    {
        $this->logUserAction($user, 'RESERVATION_CREATED', [
            'reservation_reference' => $reference,
            'quantity' => $quantity,
        ]);
    }

    public function logReservationCancelled(UserInterface $user, string $reference): void
    {
        $this->logUserAction($user, 'RESERVATION_CANCELLED', [
            'reservation_reference' => $reference,
        ]);
    }

    public function logReservationCollected(string $reference, string $employeeEmail): void
    {
        $this->logToDatabase($employeeEmail, 'RESERVATION_COLLECTED', [
            'reservation_reference' => $reference,
        ]);
    }

    /**
     * Log product creation
     *
     * @param UserInterface $user
     * @param int $productId
     * @param string $productName
     */
    public function logProductCreated(UserInterface $user, int $productId, string $productName): void
    {
        $this->logUserAction($user, 'PRODUCT_CREATED', [
            'product_id' => $productId,
            'product_name' => $productName,
        ]);
    }

    /**
     * Log product update with automatic diffing of price and stock
     *
     * @param UserInterface $user
     * @param int $productId
     * @param string $productName
     * @param float|null $oldPrice
     * @param float|null $newPrice
     * @param int|null $oldStock
     * @param int|null $newStock
     */
    public function logProductUpdated(UserInterface $user, int $productId, string $productName, ?float $oldPrice = null, ?float $newPrice = null, ?int $oldStock = null, ?int $newStock = null): void
    {
        $context = [
            'product_id' => $productId,
            'product_name' => $productName,
        ];

        if ($oldPrice !== null && $newPrice !== null && $oldPrice != $newPrice) {
            $context['old_price'] = $oldPrice;
            $context['new_price'] = $newPrice;
        }

        if ($oldStock !== null && $newStock !== null && $oldStock != $newStock) {
            $context['old_stock'] = $oldStock;
            $context['new_stock'] = $newStock;
        }

        $this->logUserAction($user, 'PRODUCT_UPDATED', $context);
    }

    public function logProductDeleted(UserInterface $user, int $productId, string $productName): void
    {
        $this->logUserAction($user, 'PRODUCT_DELETED', [
            'product_id' => $productId,
            'product_name' => $productName,
        ]);
    }

    /**
     * Log a successful login
     *
     * @param string $userEmail
     */
    public function logLogin(string $userEmail): void
    {
        $this->logToDatabase($userEmail, 'LOGIN_SUCCESS');
    }

    public function logFailedLogin(string $userEmail): void
    {
        $this->logToDatabase($userEmail, 'LOGIN_FAILED');
    }
}
