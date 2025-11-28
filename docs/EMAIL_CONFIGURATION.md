# Configuration des Notifications Email

## 📧 Système de Notifications

Le projet Noz ClicAndConnect dispose d'un système complet de notifications par email pour :
- ✅ Confirmation d'inscription
- ✅ Confirmation de réservation
- ⏰ Rappel avant expiration (à implémenter via cron)
- ⚠️ Avertissement après expiration (à implémenter via cron)

## 🔧 Configuration

### 1. Configuration SMTP dans `.env`

Par défaut, les emails sont désactivés (`MAILER_DSN=null://null`). Pour activer l'envoi d'emails, modifiez la ligne dans votre fichier `.env` :

#### Gmail (recommandé pour les tests)
```env
MAILER_DSN=gmail+smtp://votre.email@gmail.com:mot-de-passe-app@default
```

**Note :** Vous devez créer un "mot de passe d'application" dans votre compte Google :
1. Allez dans https://myaccount.google.com/security
2. Activez la validation en 2 étapes
3. Créez un mot de passe d'application

#### SMTP Générique
```env
MAILER_DSN=smtp://utilisateur:motdepasse@smtp.example.com:587
```

#### Mailtrap (pour les tests sans envoi réel)
```env
MAILER_DSN=smtp://username:password@smtp.mailtrap.io:2525
```

### 2. Personnalisation de l'expéditeur

Dans `config/services.yaml`, ajoutez :

```yaml
parameters:
    mailer.from_email: 'noreply@noz-clicandconnect.fr'
    mailer.from_name: 'Noz ClicAndConnect'

services:
    App\Service\EmailNotificationService:
        arguments:
            $fromEmail: '%mailer.from_email%'
            $fromName: '%mailer.from_name%'
```

## 📨 Emails Implémentés

### 1. Confirmation d'Inscription
- **Déclencheur :** Après création de compte
- **Template :** `templates/emails/registration_confirmation.html.twig`
- **Contenu :** Message de bienvenue avec lien vers les produits

### 2. Confirmation de Réservation
- **Déclencheur :** Après réservation d'un produit
- **Template :** `templates/emails/reservation_confirmation.html.twig`
- **Contenu :** Détails du produit, quantité, date d'expiration

### 3. Rappel d'Expiration
- **Déclencheur :** À implémenter (6h avant expiration)
- **Template :** `templates/emails/expiration_reminder.html.twig`
- **Contenu :** Rappel avec avertissement sur les strikes

### 4. Avertissement d'Expiration
- **Déclencheur :** À implémenter (après expiration)
- **Template :** `templates/emails/expiration_warning.html.twig`
- **Contenu :** Notification de strike, nombre de strikes actuels

## 🔄 Emails Automatiques (À Implémenter)

Pour les rappels et avertissements automatiques, créez une commande Symfony :

```php
// src/Command/SendExpirationRemindersCommand.php
#[AsCommand(name: 'app:send-expiration-reminders')]
class SendExpirationRemindersCommand extends Command
{
    public function __construct(
        private ReservationRepository $reservationRepository,
        private EmailNotificationService $emailService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $now = new \DateTimeImmutable();
        $reminderTime = $now->modify('+6 hours');

        // Trouver les réservations qui expirent dans 6h
        $reservations = $this->reservationRepository->createQueryBuilder('r')
            ->where('r.status = :status')
            ->andWhere('r.expiresAt BETWEEN :now AND :reminderTime')
            ->setParameter('status', 'ACTIVE')
            ->setParameter('now', $now)
            ->setParameter('reminderTime', $reminderTime)
            ->getQuery()
            ->getResult();

        foreach ($reservations as $reservation) {
            $this->emailService->sendExpirationReminder($reservation);
        }

        return Command::SUCCESS;
    }
}
```

Puis configurez un cron job :
```bash
# Exécuter toutes les heures
0 * * * * cd /path/to/project && php bin/console app:send-expiration-reminders
```

## 🧪 Test des Emails

### En mode développement (null mailer)
Les emails sont affichés dans le Symfony Profiler :
1. Effectuez une action (inscription, réservation)
2. Cliquez sur l'icône email dans la barre de debug Symfony
3. Visualisez l'email généré

### Avec Mailtrap
1. Créez un compte sur https://mailtrap.io
2. Configurez le MAILER_DSN avec vos identifiants Mailtrap
3. Tous les emails seront capturés dans votre inbox Mailtrap

## 📝 Personnalisation des Templates

Les templates email sont dans `templates/emails/` et utilisent Twig. Vous pouvez les personnaliser :

- **Couleurs :** Modifiez les couleurs dans les styles inline
- **Logo :** Ajoutez un logo en hébergeant l'image et en utilisant une URL absolue
- **Contenu :** Modifiez le texte selon vos besoins

## ⚠️ Important

- Les emails sont envoyés de manière **asynchrone** pour ne pas bloquer l'application
- Les erreurs d'envoi sont loggées mais ne bloquent pas le processus
- En production, utilisez un service SMTP fiable (SendGrid, Mailgun, Amazon SES)
