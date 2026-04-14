<?php
require __DIR__ . '/../vendor/autoload.php';

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

$pub = "BJ6vNrTw9BH44rrIbKVuKS17SOoGyGW5QjGr_BkxREA8QociDV0eTG-4Jfog2m0pn0lwwBH53rMP-y7fXl4pP3g";
$priv = "xwmjCXRYDGrqexyTr0PnqCjXaBIWZ4PrS8Q60q9L5Wc";

$auth = [
    'VAPID' => [
        'subject' => 'mailto:admin@noz-amberieu.fr',
        'publicKey' => $pub,
        'privateKey' => $priv,
    ],
];

$webPush = new WebPush($auth);

$sub = Subscription::create([
    'endpoint' => 'https://fcm.googleapis.com/fcm/send/test',
    'publicKey' => 'BLC...', // Dummy
    'authToken' => 'ABC...', // Dummy
]);

echo "Generating headers with patched library...\n";
try {
    // We can't easily get headers alone, but we can inspect the request via a mock hub?
    // Actually, I'll just use the internal VAPID class directly to see what it produces with our patch.
    
    $binaryPub = base64_decode(str_replace(['-', '_'], ['+', '/'], $pub));
    $binaryPriv = base64_decode(str_replace(['-', '_'], ['+', '/'], $priv));
    
    // We need to import the internal classes since we patched them
    $headers = \Minishlink\WebPush\VAPID::getVapidHeaders(
        'https://fcm.googleapis.com',
        'mailto:admin@noz-amberieu.fr',
        $binaryPub,
        $binaryPriv,
        \Minishlink\WebPush\ContentEncoding::aes128gcm
    );
    
    print_r($headers);
    
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
