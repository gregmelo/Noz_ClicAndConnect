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

// TENTATIVE DE DIAGNOSTIC SYNTAXE
echo "--- VÉRIFICATION SYNTAXE PHP ---\n";
$filesToCheck = [
    __DIR__ . '/../src/Twig/AppExtension.php',
    __DIR__ . '/../src/Controller/Api/ReservationApiController.php'
];

foreach ($filesToCheck as $file) {
    echo "Check: " . basename($file) . " ... ";
    if (!file_exists($file)) {
        echo "MANQUANT!\n";
        continue;
    }
    
    // Lint command via shell if possible, or try to include in a try-catch (risky but blocked)
    // We'll use formatting check
    $output = [];
    $return = 0;
    exec("php -l \"$file\"", $output, $return);
    
    if ($return === 0) {
        echo "OK (Syntaxe valide)\n";
    } else {
        echo "ERREUR DE SYNTAXE :\n";
        print_r($output);
    }
}

if (is_dir($cacheDir)) {
    recursiveDelete($cacheDir);
    echo "✅ Cache Symfony (var/cache/prod) supprimé avec succès.\n";
} else {
    echo "ℹ️ Le dossier de cache n'existait pas (déjà vide).\n";
}

echo "\nTerminé. Rechargez votre site maintenant.\n\n";
echo "--- CONTENU LOGS ---\n";
$logDir = __DIR__ . '/../var/log';
if (is_dir($logDir)) {
    $files = scandir($logDir);
    foreach($files as $f) {
        if($f == '.' || $f == '..') continue;
        echo "Fichier trouvé: $f\n";
        
        // Try to read last lines of any .log file
        if (str_ends_with($f, '.log')) {
            echo ">> Extrait de $f :\n";
            $content = file("$logDir/$f");
            $last = array_slice($content, -20);
            foreach($last as $l) echo htmlspecialchars($l);
            echo "\n----------------\n";
        }
    }
}
echo "</pre>";
