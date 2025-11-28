<?php

namespace App\Service;

use App\Entity\Reservation;
use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class EmailNotificationService
{
    public function __construct(
        private MailerInterface $mailer,
        private string $fromEmail = 'noreply@noz-clicandconnect.fr',
        private string $fromName = 'Noz ClicAndConnect'
    ) {
    }

    public function sendRegistrationConfirmation(User $user): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to($user->getEmail())
            ->subject('Bienvenue sur Noz ClicAndConnect !')
            ->htmlTemplate('emails/registration_confirmation.html.twig')
            ->context([
                'user' => $user,
            ]);

        try {
            $this->mailer->send($email);
        } catch (\Exception $e) {
            // Log error but don't block registration
            error_log('Failed to send registration email: ' . $e->getMessage());
        }
    }

    public function sendReservationConfirmation(Reservation $reservation): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to($reservation->getUser()->getEmail())
            ->subject('Confirmation de réservation')
            ->htmlTemplate('emails/reservation_confirmation.html.twig')
            ->context([
                'reservation' => $reservation,
                'user' => $reservation->getUser(),
            ]);

        try {
            $this->mailer->send($email);
        } catch (\Exception $e) {
            error_log('Failed to send reservation confirmation email: ' . $e->getMessage());
        }
    }

    public function sendExpirationReminder(Reservation $reservation): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to($reservation->getUser()->getEmail())
            ->subject('⏰ Rappel : Votre réservation expire bientôt !')
            ->htmlTemplate('emails/expiration_reminder.html.twig')
            ->context([
                'reservation' => $reservation,
                'user' => $reservation->getUser(),
            ]);

        try {
            $this->mailer->send($email);
        } catch (\Exception $e) {
            error_log('Failed to send expiration reminder email: ' . $e->getMessage());
        }
    }

    public function sendExpirationWarning(Reservation $reservation): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to($reservation->getUser()->getEmail())
            ->subject('⚠️ Votre réservation a expiré')
            ->htmlTemplate('emails/expiration_warning.html.twig')
            ->context([
                'reservation' => $reservation,
                'user' => $reservation->getUser(),

            ]);

        try {
            $this->mailer->send($email);
        } catch (\Exception $e) {
            error_log('Failed to send expiration warning email: ' . $e->getMessage());
        }
    }
    public function sendReadyNotification(Reservation $reservation): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to($reservation->getUser()->getEmail())
            ->subject('🚀 Votre commande est prête !')
            ->htmlTemplate('emails/ready_notification.html.twig')
            ->context([
                'reservation' => $reservation,
                'user' => $reservation->getUser(),
            ]);

        try {
            $this->mailer->send($email);
        } catch (\Exception $e) {
            error_log('Failed to send ready notification email: ' . $e->getMessage());
        }
    }
}
