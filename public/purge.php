<?php
// public/purge.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Purge Totale du Cache</h1><pre>";

$cacheBase = __DIR__ . '/../var/cache';

function rmdir_recursive($dir) {
    if (!is_dir($dir)) return;
    $files = array_diff(scandir($dir), array('.','..'));
    foreach ($files as $file) {
        (is_dir("$dir/$file")) ? rmdir_recursive("$dir/$file") : unlink("$dir/$file");
    }
    echo "Suppression de : $dir\n";
    return rmdir($dir);
}

if (is_dir($cacheBase)) {
    $envs = array_diff(scandir($cacheBase), array('.', '..'));
    foreach ($envs as $env) {
        echo "Nettoyage de l'environnement : $env...\n";
        rmdir_recursive($cacheBase . '/' . $env);
    }
}

echo "\n--- Vérification finale ---\n";
if (file_exists(__DIR__ . '/../.env.local.php')) {
    echo "⚠️ Attention : Un fichier .env.local.php existe. Il peut écraser vos réglages.\n";
}

echo "\nTerminé. Rechargez votre site (Ctrl+F5).";
echo "</pre>";
