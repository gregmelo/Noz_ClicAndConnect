<?php

namespace App\Service;

use App\Entity\PushSubscription;
use App\Entity\User;
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;
use Psr\Log\LoggerInterface;

class PushNotificationService
{
    private WebPush $webPush;

    public function __construct(
        private string $vapidPublicKey,
        private string $vapidPrivateKey,
        private LoggerInterface $logger,
    ) {
        // Fix for Windows/XAMPP: ensure OpenSSL can find its config file
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $opensslConfig = 'C:\xampp\php\extras\ssl\openssl.cnf';
            if (file_exists($opensslConfig)) {
                putenv("OPENSSL_CONF=$opensslConfig");
            }
        }

        // Clean keys to avoid hidden characters or quotes issues
        $cleanPublic = trim($this->vapidPublicKey, " \t\n\r\0\x0B\"");
        $cleanPrivate = trim($this->vapidPrivateKey, " \t\n\r\0\x0B\"");

        $auth = [
            'VAPID' => [
                'subject' => 'mailto:admin@noz-amberieu.fr',
                'publicKey' => $cleanPublic,
                'privateKey' => $cleanPrivate,
            ],
        ];

        // Direct initialization. If it fails here, Symfony will show a clear error.
        $this->webPush = new WebPush($auth);
    }

    /**
     * Send notification to a specific user
     */
    public function sendToUser(User $user, string $title, string $body, string $url = '/'): void
    {
        foreach ($user->getPushSubscriptions() as $sub) {
            $this->sendToSubscription($sub, $title, $body, $url);
        }
    }

    /**
     * Send notification to a specific subscription
     */
    public function sendToSubscription(PushSubscription $sub, string $title, string $body, string $url = '/'): void
    {
        $subscription = Subscription::create([
            'endpoint' => $sub->getEndpoint(),
            'publicKey' => $sub->getPublicKey(),
            'authToken' => $sub->getAuthToken(),
            'contentEncoding' => $sub->getContentEncoding(),
        ]);

        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'url' => $url,
        ]);

        if (!isset($this->webPush)) {
            $this->logger->error("[PushNotificationService] WebPush not initialized. Skipping notification.");
            return;
        }

        $this->webPush->queueNotification($subscription, $payload);
    }

    /**
     * Flush all queued notifications
     */
    public function flush(): void
    {
        /** @var \Minishlink\WebPush\MessageSentReport $report */
        foreach ($this->webPush->flush() as $report) {
            $endpoint = $report->getEndpoint();
            if (!$report->isSuccess()) {
                $this->logger->error("[WebPush] Message failed to sent for subscription {$endpoint}: {$report->getReason()}");
            } else {
                $this->logger->info("[WebPush] Message successfully sent to {$endpoint}");
            }
        }
    }
}
