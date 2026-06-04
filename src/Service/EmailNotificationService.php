<?php
//EmailNotificationService.php
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

        $this->mailer->send($email);
    }

    public function sendReservationConfirmation(Reservation $reservation): void
    {
        // Si l'utilisateur a activé les notifications push (PWA), on évite de doubler avec un email
        $user = $reservation->getUser();
        if (method_exists($user, 'getPushSubscriptions') && $user->getPushSubscriptions()->count() > 0) {
            return;
        }

        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to($reservation->getUser()->getEmail())
            ->subject('Confirmation de réservation')
            ->htmlTemplate('emails/reservation_confirmation.html.twig')
            ->context([
                'reservation' => $reservation,
                'user' => $reservation->getUser(),
            ]);

        $this->mailer->send($email);
    }

    public function sendExpirationReminder(Reservation $reservation): void
    {
        $user = $reservation->getUser();
        if (method_exists($user, 'getPushSubscriptions') && $user->getPushSubscriptions()->count() > 0) {
            return;
        }

        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to($reservation->getUser()->getEmail())
            ->subject('⏰ Rappel : Votre réservation expire bientôt !')
            ->htmlTemplate('emails/expiration_reminder.html.twig')
            ->context([
                'reservation' => $reservation,
                'user' => $reservation->getUser(),
            ]);

        $this->mailer->send($email);
    }

    public function sendExpirationWarning(Reservation $reservation): void
    {
        $user = $reservation->getUser();
        if (method_exists($user, 'getPushSubscriptions') && $user->getPushSubscriptions()->count() > 0) {
            return;
        }

        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to($reservation->getUser()->getEmail())
            ->subject('⚠️ Votre réservation a expiré')
            ->htmlTemplate('emails/expiration_warning.html.twig')
            ->context([
                'reservation' => $reservation,
                'user' => $reservation->getUser(),

            ]);

        $this->mailer->send($email);
    }
    public function sendReadyNotification(Reservation $reservation): void
    {
        $user = $reservation->getUser();
        if (method_exists($user, 'getPushSubscriptions') && $user->getPushSubscriptions()->count() > 0) {
            return;
        }

        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to($reservation->getUser()->getEmail())
            ->subject('🚀 Votre commande est prête !')
            ->htmlTemplate('emails/ready_notification.html.twig')
            ->context([
                'reservation' => $reservation,
                'user' => $reservation->getUser(),
            ]);

        $this->mailer->send($email);
    }
}
