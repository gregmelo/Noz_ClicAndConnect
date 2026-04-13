<?php

namespace App\Message;

/**
 * ReservationNotificationMessage
 * 
 * Sent after a reservation is created to trigger asynchronous 
 * notifications (Email/Push).
 */
class ReservationNotificationMessage
{
    public function __construct(
        private int $reservationId,
    ) {
    }

    public function getReservationId(): int
    {
        return $this->reservationId;
    }
}
