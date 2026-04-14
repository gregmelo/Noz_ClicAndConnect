<?php

namespace App\Service;

use App\Entity\PushSubscription;
use App\Entity\User;
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;
use Psr\Log\LoggerInterface;

/**
 * Service gérant l'envoi de notifications Web Push via le protocole VAPID.
 * 
 * Ce service utilise la bibliothèque Minishlink/WebPush pour communiquer avec les serveurs
 * de push (Google FCM, Apple Push, etc.) et gère les spécificités liées à l'environnement Windows.
 */
class PushNotificationService
{
    private WebPush $webPush;

    public function __construct(
        private string $vapidPublicKey,
        private string $vapidPrivateKey,
        private LoggerInterface $logger,
    ) {
        // --- FIX WINDOWS / XAMPP ---
        // S'assure qu'OpenSSL peut trouver son fichier de configuration.
        // Sans cela, la génération des signatures VAPID échoue sur certains serveurs Windows.
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $opensslConfig = 'C:\xampp\php\extras\ssl\openssl.cnf';
            if (file_exists($opensslConfig)) {
                putenv("OPENSSL_CONF=$opensslConfig");
            }
        }

        // Nettoyage des clés VAPID pour supprimer les caractères invisibles ou quotes parasites
        $cleanPublic = trim($this->vapidPublicKey, " \t\n\r\0\x0B\"");
        $cleanPrivate = trim($this->vapidPrivateKey, " \t\n\r\0\x0B\"");

        // Configuration de l'authentification VAPID
        $auth = [
            'VAPID' => [
                'subject' => 'mailto:admin@noz-amberieu.fr',
                'publicKey' => $cleanPublic,
                'privateKey' => $cleanPrivate, // Note: Nécessite le format PKCS#8 sur OpenSSL 3.0+
            ],
        ];

        // Initialisation de la bibliothèque WebPush
        $this->webPush = new WebPush($auth);
    }

    /**
     * Envoie une notification à toutes les souscriptions d'un utilisateur donné.
     * 
     * @param User $user L'utilisateur destinataire
     * @param string $title Titre de la notification
     * @param string $body Contenu du message
     * @param string $url URL vers laquelle rediriger au clic
     */
    public function sendToUser(User $user, string $title, string $body, string $url = '/'): void
    {
        foreach ($user->getPushSubscriptions() as $sub) {
            $this->sendToSubscription($sub, $title, $body, $url);
        }
    }

    /**
     * Ajoute une notification à la file d'attente pour une souscription spécifique.
     * 
     * @param PushSubscription $sub Entité contenant les clés de souscription
     * @param string $title Titre de la notification
     * @param string $body Contenu du message
     * @param string $url URL cible
     */
    public function sendToSubscription(PushSubscription $sub, string $title, string $body, string $url = '/'): void
    {
        // Création de l'objet de souscription conforme à la norme Web Push
        $subscription = Subscription::create([
            'endpoint' => $sub->getEndpoint(),
            'publicKey' => $sub->getPublicKey(),
            'authToken' => $sub->getAuthToken(),
            'contentEncoding' => $sub->getContentEncoding(),
        ]);

        // Construction du payload JSON
        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'url' => $url,
        ]);

        if (!isset($this->webPush)) {
            $this->logger->error("[PushNotificationService] WebPush non initialisé.");
            return;
        }

        // Ajout à la file d'attente (nécessite un appel à flush() pour l'envoi réel)
        $this->webPush->queueNotification($subscription, $payload);
    }

    /**
     * Déclenche l'envoi effectif de toutes les notifications en attente.
     * Analyse les rapports de succès/échec et les enregistre dans les logs Symfony.
     */
    public function flush(): void
    {
        /** @var \Minishlink\WebPush\MessageSentReport $report */
        foreach ($this->webPush->flush() as $report) {
            $endpoint = $report->getEndpoint();
            if (!$report->isSuccess()) {
                $this->logger->error("[WebPush] Échec d'envoi pour {$endpoint} : {$report->getReason()}");
            } else {
                $this->logger->info("[WebPush] Notification envoyée avec succès à {$endpoint}");
            }
        }
    }
}
