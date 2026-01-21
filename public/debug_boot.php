<?php
/**
 * debug_boot.php - Diagnostic for 500 error
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 Diagnostic Boot</h1>";

$rootDir = dirname(__DIR__);
$envLocalPhp = $rootDir . '/.env.local.php';

// --- ACTION DE NETTOYAGE D'URGENCE ---
if (isset($_GET['action'])) {
    if ($_GET['action'] === 'fix_src') {
        $duplicateSrc = $rootDir . '/src/src';
        if (is_dir($duplicateSrc)) {
            echo "<div style='background:#fde; padding:10px; border:1px solid #f00;'>";
            echo "⚠️ Tentative de suppression de $duplicateSrc...<br>";
            function rrmdir_src($dir) {
                foreach(glob($dir . '/{,.}[!.,..]*', GLOB_BRACE) as $file) {
                    if(is_dir($file)) rrmdir_src($file); else unlink($file);
                }
                return rmdir($dir);
            }
            if (rrmdir_src($duplicateSrc)) {
                echo "✅ Dossier en double supprimé avec succès !<br>";
                echo "<a href='debug_boot.php'>Cliquez ici pour relancer le diagnostic.</a>";
                die("</div>");
            } else { echo "❌ Échec de la suppression."; }
            echo "</div>";
        }
    }
    
    if ($_GET['action'] === 'purge_cache') {
        echo "<div style='background:#fde; padding:10px; border:1px solid #f00;'>";
        $cacheDir = $rootDir . '/var/cache/prod';
        if (is_dir($cacheDir)) {
            echo "⚠️ Purge de $cacheDir...<br>";
            function rrmdir_cache($dir) {
                foreach(glob($dir . '/{,.}[!.,..]*', GLOB_BRACE) as $file) {
                    if(is_dir($file)) rrmdir_cache($file); else unlink($file);
                }
                return rmdir($dir);
            }
            if (rrmdir_cache($cacheDir)) { echo "✅ Cache de production vidé !<br>"; }
            else { echo "❌ Échec du vidage du cache.<br>"; }
        } else { echo "ℹ️ Dossier de cache déjà absent.<br>"; }
        echo "<a href='debug_boot.php'>Relancer le diagnostic.</a>";
        die("</div>");
    }

    if ($_GET['action'] === 'reset_opcache') {
        echo "<div style='background:#fde; padding:10px; border:1px solid #f00;'>";
        if (function_exists('opcache_reset')) {
            opcache_reset();
            echo "✅ OPcache réinitialisé ! Les fichiers seront rechargés du disque.<br>";
        } else { echo "❌ OPcache non disponible.<br>"; }
        echo "<a href='debug_boot.php'>Relancer le diagnostic.</a>";
        die("</div>");
    }

    if ($_GET['action'] === 'empty_subs') {
        echo "<div style='background:#fde; padding:10px; border:1px solid #f00;'>";
        try {
            require_once $rootDir . '/vendor/autoload.php';
            // Use PDO created later or create a new one here
            $config = include $envLocalPhp;
            $url = parse_url($config['DATABASE_URL']);
            $pdo = new PDO("mysql:host={$url['host']};dbname=".ltrim($url['path'],'/'), urldecode($url['user']), urldecode($url['pass']));
            $pdo->exec("DELETE FROM push_subscription");
            echo "✅ Tous les abonnements ont été supprimés de la base de données !<br>";
        } catch (\Exception $e) {
            echo "❌ Erreur : " . $e->getMessage() . "<br>";
        }
        echo "<a href='debug_boot.php'>Retour au diagnostic.</a>";
        die("</div>");
    }
}

echo "<h2>🌐 État des Variables d'Environnement</h2>";
echo "getenv(VAPID_PUBLIC_KEY): <code>" . (getenv('VAPID_PUBLIC_KEY') ?: 'VIDE') . "</code><br>";
echo "\$_ENV[VAPID_PUBLIC_KEY]: <code>" . ($_ENV['VAPID_PUBLIC_KEY'] ?? 'VIDE') . "</code><br>";
echo "\$_SERVER[VAPID_PUBLIC_KEY]: <code>" . ($_SERVER['VAPID_PUBLIC_KEY'] ?? 'VIDE') . "</code><br>";

$envFile = $rootDir . '/.env';
if (file_exists($envFile)) {
    echo "<h3>Sniff .env :</h3>";
    foreach (file($envFile) as $line) {
        if (strpos($line, 'VAPID_PUBLIC_KEY') !== false) {
            echo "Trouvé dans .env : <code>" . htmlspecialchars(trim($line)) . "</code><br>";
        }
    }
}


echo "<h2>📂 Informations Serveur & Fichiers</h2>";
echo "Script path: <code>" . __FILE__ . "</code><br>";
echo "Root Dir: <code>" . realpath($rootDir) . "</code><br>";

echo "<h3>Fichiers dans le Root :</h3><pre>";
$files = scandir($rootDir);
foreach ($files as $f) {
    if ($f === '.' || $f === '..') continue;
    $time = date("Y-m-d H:i:s", filemtime($rootDir . '/' . $f));
    echo str_pad($f, 30) . " | $time | " . (is_dir($rootDir . '/' . $f) ? 'DIR' : filesize($rootDir . '/' . $f) . ' bytes') . "\n";
}
echo "</pre>";

$parentDir = dirname($rootDir);
echo "<h3>Fichiers dans le Parent (" . realpath($parentDir) . ") :</h3><pre>";
$pFiles = scandir($parentDir);
foreach ($pFiles as $f) {
    if ($f === '.' || $f === '..') continue;
    $time = date("Y-m-d H:i:s", filemtime($parentDir . '/' . $f));
    echo str_pad($f, 30) . " | $time | " . (is_dir($parentDir . '/' . $f) ? 'DIR' : filesize($parentDir . '/' . $f) . ' bytes') . "\n";
}
echo "</pre>";

echo "<h2>1. Vérification de .env.local.php</h2>";

if (file_exists($envLocalPhp)) {
    echo "✅ Fichier présent.<br>";
    try {
        $config = include $envLocalPhp;
        if (is_array($config)) {
            echo "✅ Fichier syntaxiquement correct.<br>";
            echo "APP_ENV : " . ($config['APP_ENV'] ?? 'Non défini') . "<br>";
            echo "DATABASE_URL (masqué) : " . (isset($config['DATABASE_URL']) ? 'Présent' : 'ABSENT') . "<br>";
            
            $key = $config['VAPID_PUBLIC_KEY'] ?? '';
            echo "VAPID_PUBLIC_KEY : " . ($key ? 'Présent' : 'ABSENT') . "<br>";
            if ($key) {
                echo "-> Valeur : <code style='background:#eee; padding:2px;'>$key</code><br>";
                echo "-> Longueur : " . strlen($key) . " caractères (doit être 87)<br>";
            }
        } else {
            echo "❌ Le fichier ne retourne pas un tableau.<br>";
        }
    } catch (\Throwable $e) {
        echo "❌ Erreur lors de l'inclusion : " . $e->getMessage() . "<br>";
    }
} else {
    echo "❌ Fichier .env.local.php MANQUANT.<br>";
}

echo "<h2>2. Test de connexion Base de Données</h2>";
if (isset($config['DATABASE_URL'])) {
    $url = parse_url($config['DATABASE_URL']);
    $host = $url['host'] ?? '';
    // IMPORTANT: Symfony URL-encodes these, but PDO needs them decoded
    $user = urldecode($url['user'] ?? '');
    $pass = urldecode($url['pass'] ?? '');
    $db = ltrim(urldecode($url['path'] ?? ''), '/');
    
    echo "Host: $host<br>";
    echo "Database: $db<br>";
    echo "User: $user<br>";
    echo "Pass (longueur): " . strlen($pass) . "<br>";
    
    echo "Tentative de connexion à $host ($db)...<br>";
    try {
        // Force IPv4 if needed by using gethostbyname? No, let PDO handle it.
        $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5
        ]);
        echo "✅ Connexion MySQL réussie !<br>";
        
        // --- NOUVEAUX CHECKS PUSH ---
        echo "<h3>📊 État des Abonnements Push</h3>";
        $stmt = $pdo->query("SELECT COUNT(*) FROM push_subscription");
        $count = $stmt->fetchColumn();
        echo "Nombre d'abonnements en base : <b>$count</b><br>";
        
        if ($count > 0) {
            $stmt = $pdo->query("SELECT u.email, ps.endpoint, ps.created_at FROM push_subscription ps LEFT JOIN `user` u ON ps.user_id = u.id ORDER BY ps.id DESC LIMIT 5");
            echo "Derniers abonnés :<ul>";
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $truncated = substr($row['endpoint'], 0, 40) . '...';
                echo "<li>" . ($row['email'] ?? 'Visiteur') . " ($truncated) - <b>Créé le : {$row['created_at']}</b></li>";
            }
            echo "</ul>";
        }
    } catch (\Exception $e) {
        echo "❌ Échec de connexion MySQL : " . $e->getMessage() . "<br>";
        
        // Try localhost if host fails? Alwaysdata sometimes wants 'localhost' or '127.0.0.1' inside the same server.
        if ($host !== 'localhost' && $host !== '127.0.0.1') {
            echo "<br><i>Note: Tentative alternative sur localhost...</i><br>";
            try {
                $pdo2 = new PDO("mysql:host=localhost;dbname=$db;charset=utf8mb4", $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 2
                ]);
                echo "✅ Connexion MySQL réussie via localhost ! (Modifiez DATABASE_URL dans build.php)<br>";
            } catch (\Exception $e2) {
                echo "❌ Échec via localhost également.<br>";
            }
        }
    }
}

echo "<h2>4. Logs de production (dernières lignes)</h2>";
$logFile = $rootDir . '/var/log/prod.log';
if (file_exists($logFile)) {
    echo "📄 var/log/prod.log présent.<br>";
    $lines = file($logFile);
    $lastLines = array_slice($lines, -20);
    echo "<pre style='background:#000; color:#0f0; padding:10px; overflow:auto;'>";
    foreach ($lastLines as $line) {
        echo htmlspecialchars($line);
    }
    echo "</pre>";
} else {
    echo "📄 var/log/prod.log ABSENT. Essayez d'accéder à la page d'accueil d'abord pour générer une erreur.<br>";
}

echo "<h2>5. Test de chargement manuel du Kernel</h2>";
try {
    require_once $rootDir . '/vendor/autoload.php';
    echo "✅ Autoload chargé.<br>";
    if (class_exists('App\Kernel')) {
        echo "✅ Classe App\Kernel trouvée. Tentative de boot...<br>";
        
        // This is where we catch the real error
        ob_start();
        $kernel = new \App\Kernel($config['APP_ENV'] ?? 'prod', (bool)($config['APP_DEBUG'] ?? false));
        $kernel->boot();
        echo "✅ Kernel de prod BOOTÉ avec succès !<br>";
        ob_end_clean();
    } else {
        echo "❌ Classe App\Kernel INTROUVABLE.<br>";
    }
} catch (\Throwable $e) {
    if (ob_get_level()) ob_end_clean();
    echo "<div style='background:#fee; border:1px solid #f99; padding:15px; margin:10px 0;'>";
    echo "<h3 style='color:#c00; margin:0;'>❌ ERREUR FATALE CAPTURÉE :</h3>";
    echo "<p><strong>Message :</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Fichier :</strong> " . $e->getFile() . " à la ligne " . $e->getLine() . "</p>";
    echo "<details><summary>Trace complète</summary><pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre></details>";
    echo "</div>";
    
    echo "<h4>💡 Suggestions :</h4><ul>";
    if (strpos($e->getMessage(), 'VAPID') !== false) {
        echo "<li>Le problème semble lié aux clés VAPID. Vérifiez leur format dans build.php.</li>";
    }
    if (strpos($e->getMessage(), 'Service') !== false || strpos($e->getMessage(), 'dependency') !== false) {
        echo "<li>Une dépendance ou un service est mal configuré. Essayez de vider manuellement <code>var/cache/prod</code>.</li>";
    }
    echo "</ul>";
}

echo "<h2>6. Vérification du Service Worker</h2>";
$swFile = $rootDir . '/public/sw.js';
if (file_exists($swFile)) {
    echo "✅ Fichier <code>sw.js</code> présent.<br>";
    $swContent = file_get_contents($swFile);
    if (strpos($swContent, 'CACHE_NAME') !== false) {
        preg_match("/CACHE_NAME = '(.*?)'/", $swContent, $m);
        $swVersion = $m[1] ?? 'Inconnue';
        echo "-> Version du cache SW : <b>$swVersion</b><br>";
        if ($swVersion === 'noz-clic-cache-v1') {
            echo "⚠️ Attention : Vous êtes encore en <b>v1</b>. Actualisez la page pour passer en <b>v2</b> (ce que je viens de modifier).<br>";
        }
    }
}

echo "<h2>7. Recherche de conflits de Meta Tags</h2>";
// On va tenter de faire une requête HTTP locale pour voir ce que le serveur envoie réellement
$siteUrl = "https://nozclicandcollect.alwaysdata.net/";
echo "Analyse du HTML rendu sur $siteUrl...<br>";

$html = file_get_contents($siteUrl, false, stream_context_create([
    "ssl" => ["verify_peer" => false, "verify_peer_name" => false],
    "http" => ["header" => "User-Agent: Moz-Debug-Script\r\n"]
]));

if ($html) {
    if (preg_match('/<meta name="vapid-public-key" content="(.*?)">/', $html, $matches)) {
        $renderedKey = trim($matches[1]);
        echo "✅ Meta tag trouvé !<br>";
        echo "-> Valeur dans le HTML : <code id='html-key' style='background:#eee; padding:2px;'>" . htmlspecialchars($renderedKey). "</code><br>";
        echo "-> Longueur dans le HTML : " . strlen($renderedKey) . " chars<br>";
        
        echo "<div id='js-test-result' style='margin-top:10px; padding:10px; border-radius:5px; background:#f0f7ff; border:1px solid #cce3ff;'>";
        echo "<b>🧪 Test client (JavaScript) :</b> <span id='js-status'>Analyse en cours...</span>";
        echo "</div>";

        echo "<script>
        (function() {
            const keyStr = document.getElementById('html-key').textContent.trim();
            const status = document.getElementById('js-status');
            try {
                const padding = '='.repeat((4 - keyStr.length % 4) % 4);
                const base64 = (keyStr + padding).replace(/-/g, '+').replace(/_/g, '/');
                const rawData = window.atob(base64);
                const outputArray = new Uint8Array(rawData.length);
                for (let i = 0; i < rawData.length; ++i) { outputArray[i] = rawData.charCodeAt(i); }
                
                if (outputArray.length === 65) {
                    const firstByte = outputArray[0];
                    if (firstByte === 4) {
                        status.innerHTML = '✅ <b>Clé valide (65 bytes, starts with 0x04)</b>. Le navigateur DOIT l\'accepter.';
                        status.style.color = 'green';
                    } else {
                        status.innerHTML = '⚠️ <b>Clé 65 bytes mais ne commence pas par 0x04</b> (reçu: ' + firstByte + '). Safari risque d\'échouer.';
                        status.style.color = 'orange';
                    }
                } else {
                    status.innerHTML = '❌ <b>Clé invalide (' + outputArray.length + ' bytes)</b>. Doit faire 65 bytes.';
                    status.style.color = 'red';
                }
            } catch (e) {
                status.innerHTML = '❌ <b>Erreur de décodage</b> : ' + e.message;
                status.style.color = 'red';
            }
        })();
        </script>";
        
        // --- TEST PUSH MANUEL ---
        echo "<div style='background:#f9f9f9; border:1px solid #ddd; padding:15px; margin:20px 0; border-radius:10px;'>";
        echo "<h3>🧪 Envoyer un Test Push</h3>";
        echo "<p>Ceci enverra une notification à TOUS les abonnements enregistrés dans la DB.</p>";
        echo "<form method='POST'>
                <input type='hidden' name='action' value='test_push'>
                <button type='submit' style='background:#003399; color:white; padding:10px 20px; border:none; border-radius:5px; cursor:pointer;'>
                    🚀 Envoyer la notification de test
                </button>
              </form>";

        if (isset($_POST['action']) && $_POST['action'] === 'test_push') {
            try {
                require_once $rootDir . '/vendor/autoload.php';
                $pub = $config['VAPID_PUBLIC_KEY'] ?? getenv('VAPID_PUBLIC_KEY');
                $priv = $config['VAPID_PRIVATE_KEY'] ?? getenv('VAPID_PRIVATE_KEY');
                
                $auth = [
                    'VAPID' => [
                        'subject' => 'mailto:admin@noz.com',
                        'publicKey' => $pub,
                        'privateKey' => $priv,
                    ],
                ];
                $webPush = new \Minishlink\WebPush\WebPush($auth);
                
                echo "<div style='background:#e7f3ff; padding:10px; margin-top:10px;'>";
                echo "Récupération des abonnements...<br>";
                $stmt = $pdo->query("SELECT * FROM push_subscription");
                $subs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (empty($subs)) {
                    echo "❌ Aucun abonnement trouvé en base.";
                } else {
                    foreach ($subs as $row) {
                        $webPush->queueNotification(
                            \Minishlink\WebPush\Subscription::create([
                                'endpoint' => $row['endpoint'],
                                'publicKey' => $row['p256dh'] ?? ($row['public_key'] ?? ''),
                                'authToken' => $row['auth'] ?? ($row['auth_token'] ?? ''),
                            ]),
                            json_encode(['title' => 'Test NOZ', 'body' => 'Notification de test via debug_boot.php', 'url' => '/'])
                        );
                    }
                    
                    echo "<h3>Résultats de l'envoi :</h3><ul>";
                    foreach ($webPush->flush() as $report) {
                        $endpoint = $report->getEndpoint();
                        $status = $report->isSuccess() ? '✅ SUCCÈS' : '❌ ÉCHEC (' . $report->getReason() . ')';
                        echo "<li>" . substr($endpoint, 0, 50) . "... : <b>$status</b></li>";
                    }
                    echo "</ul>";
                }
                echo "</div>";
            } catch (\Exception $e) {
                echo "❌ Erreur lors du test : " . $e->getMessage();
            }
        }
        echo "</div>";
        
        if ($renderedKey !== $key) {
           // ... (rest of logic)
            echo "<div style='background:#fee; border:1px solid #f99; padding:10px; margin:10px 0;'>";
            echo "❌ DISCORDANCE DÉTECTÉE : Le HTML contient une clé différente de .env.local.php !<br>";
            echo "Cela signifie que le cache Symfony n'est pas à jour ou que le fichier base.html.twig est mal lu.";
            echo "</div>";
        } else {
            echo "✅ Cohérence parfaite entre le HTML et la configuration.<br>";
        }
    } else {
        echo "❌ Meta tag 'vapid-public-key' INTROUVABLE dans le HTML !<br>";
        echo "<i>Note: Vérifiez si vous êtes sur une page de login qui n'inclut pas le meta tag.</i><br>";
    }
} else {
    echo "❌ Impossible de lire le HTML du site (Erreur loopback ou SSL).<br>";
}

echo "<div style='background:#fff3cd; border:1px solid #ffeeba; padding:15px; margin:20px 0; border-radius:5px;'>";
echo "<h3 style='margin-top:0;'>💡 ACTIONS DE DERNIER RECOURS</h3>";
echo "Si vous avez toujours l'erreur P-256 et que la clé fait 87 caractères :<br><br>";
echo "1. <b>Vider manuellement le cache</b> : Supprimez tout le contenu de <code>var/cache/prod/</code> via FTP.<br>";
echo "2. <b>Vider tous les abonnements</b> : <a href='debug_boot.php?action=empty_subs' style='color:#c00; font-weight:bold;'>CLIQUEZ ICI pour supprimer tous les jetons de la base</a> (Force tout le monde à se ré-abonner proprement).<br>";
echo "3. <b>Vérifier Alwaysdata</b> : Allez dans l'interface Alwaysdata > Web > Sites > Redémarrer le site.<br>";
echo "4. <b>Changer de clé</b> : Si rien ne marche, je générerai une nouvelle clé plus longue (88 chars).";
echo "</div>";

echo "<hr><p>Diagnostic terminé.</p>";




