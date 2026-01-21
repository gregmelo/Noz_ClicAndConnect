<?php
/**
 * vapid_generator.php - Create fresh VAPID keys
 */
require_once __DIR__ . '/../vendor/autoload.php';

use Minishlink\WebPush\VAPID;

header('Content-Type: text/html; charset=utf-8');

echo "<h1>🔑 Générateur de clés VAPID</h1>";

try {
    $keys = VAPID::createVapidKeys();
    
    echo "<div style='background:#f4f4f4; padding:20px; border-radius:10px; font-family:monospace; border:1px solid #ddd;'>";
    echo "<h3>Copiez ces valeurs dans votre fichier build.php :</h3>";
    
    echo "<b>VAPID_PUBLIC_KEY :</b><br>";
    echo "<input type='text' value='{$keys['publicKey']}' style='width:100%; padding:10px; margin:10px 0; border:1px solid #003399; font-weight:bold;' readonly><br>";
    
    echo "<b>VAPID_PRIVATE_KEY :</b><br>";
    echo "<input type='text' value='{$keys['privateKey']}' style='width:100%; padding:10px; margin:10px 0; border:1px solid #b91c1c;' readonly><br>";
    echo "</div>";
    
    echo "<p style='color:#666;'>⚠️ <i>Note : Si vous changez les clés, tous les utilisateurs déjà abonnés devront se réabonner.</i></p>";
    echo "<p>Une fois les clés copiées dans <code>build.php</code>, relancez le build et vider le cache.</p>";

} catch (\Exception $e) {
    echo "<p style='color:red;'>Erreur lors de la génération : {$e->getMessage()}</p>";
}
