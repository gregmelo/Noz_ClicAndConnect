<?php
/**
 * deploy.php - Granular Deployment Script
 * Use this to run deployment steps individually if the full process times out (503).
 */
$secret = 'MonSecret';
if (($_GET['key'] ?? '') !== $secret) {
    header('HTTP/1.0 403 Forbidden');
    die('Accès refusé');
}

// Increase limits for heavy tasks
set_time_limit(300);
ini_set('memory_limit', '512M');

$step = $_GET['step'] ?? 'menu';
$rootDir = dirname(__DIR__);

echo "<!DOCTYPE html><html><head><title>Déploiement Noz</title>";
echo "<style>body{font-family:sans-serif;padding:20px;line-height:1.5;} pre{background:#f4f4f4;padding:10px;border-radius:5px;overflow:auto;} .btn{display:inline-block;padding:8px 15px;background:#003399;color:white;text-decoration:none;border-radius:4px;margin:5px 0;} .btn:hover{background:#002266;}</style>";
echo "</head><body><h1>🛠️ Déploiement Noz ClicAndConnect</h1>";

if ($step === 'menu') {
    echo "<p>Choisissez une étape à exécuter :</p>";
    echo "<ul>";
    echo "<li><a class='btn' href='?key=$secret&step=tailwind'>1. Compiler Tailwind CSS</a> (Nécessaire pour le design)</li>";
    echo "<li><a class='btn' href='?key=$secret&step=assets'>2. Compiler AssetMapper</a> (Indispensable pour vos modifications Push/Notification)</li>";
    echo "<li><a class='btn' href='?key=$secret&step=cache'>3. Vider le Cache Symfony</a></li>";
    echo "<li><a class='btn' href='?key=$secret&step=all'>Exécuter 1+2+3 d'un coup</a> (Risque de 503)</li>";
    echo "<li><a class='btn' style='background:#b91c1c' href='?key=$secret&step=composer'>⚠️ Composer Install</a> (Très lourd, à éviter si possible)</li>";
    echo "</ul>";
    echo "<p>💡 <i>Si vous obtenez une erreur 503, essayez de lancer les étapes 1, 2 et 3 séparément.</i></p>";
    die("</body></html>");
}

echo "<pre>";
chdir($rootDir);

function run($cmd) {
    echo "<strong>> $cmd</strong>\n";
    // Using passthru to see output in real time if supported
    passthru($cmd . ' 2>&1');
    echo "\n" . str_repeat('-', 50) . "\n\n";
}

switch ($step) {
    case 'composer':
        run('composer install --optimize-autoloader --no-dev --no-interaction');
        break;
    case 'tailwind':
        run('php bin/console tailwind:build --minify');
        break;
    case 'assets':
        run('php bin/console asset-map:compile');
        break;
    case 'cache':
        run('php bin/console cache:clear');
        break;
    case 'all':
        run('php bin/console tailwind:build --minify');
        run('php bin/console asset-map:compile');
        run('php bin/console cache:clear');
        break;
    default:
        echo "Étape inconnue.";
}

echo "</pre>";
echo "<a class='btn' href='?key=$secret'>Retour au menu</a>";
echo "</body></html>";
