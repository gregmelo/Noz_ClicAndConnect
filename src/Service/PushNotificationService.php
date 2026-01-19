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
        $auth = [
            'VAPID' => [
                'subject' => 'mailto:admin@noz-amberieu.fr', // Should be configurable
                'publicKey' => $vapidPublicKey,
                'privateKey' => $vapidPrivateKey,
            ],
        ];

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
            }
        }
    }
}
