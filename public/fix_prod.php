<?php
// public/fix_prod.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Nettoyage Cache Serveur</h1><pre>";

$cacheDir = __DIR__ . '/../var/cache/prod';

function recursiveDelete($dir) {
    if (!is_dir($dir)) return;
    $files = array_diff(scandir($dir), array('.', '..'));
    foreach ($files as $file) {
        (is_dir("$dir/$file")) ? recursiveDelete("$dir/$file") : unlink("$dir/$file");
    }
    return rmdir($dir);
}

if (is_dir($cacheDir)) {
    recursiveDelete($cacheDir);
    echo "✅ Cache Symfony (var/cache/prod) supprimé avec succès.\n";
} else {
    echo "ℹ️ Le dossier de cache n'existait pas (déjà vide).\n";
}

echo "\nTerminé. Rechargez votre site maintenant.";
echo "</pre>";
