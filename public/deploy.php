<?php
// deploy.php - Étape 6 : Tailwind & Assets
$secret = 'MonSecret';
if (($_GET['key'] ?? '') !== $secret) die('Accès refusé');

set_time_limit(600);

echo "<h1>Construction des Assets (Tailwind)</h1><pre>";
chdir('..');

function run($cmd) {
    echo "<strong>> $cmd</strong>\n";
    passthru($cmd . ' 2>&1');
    echo "\n---------------------------------------------------\n\n";
}

// 1. Installation (pour être sûr d'avoir le binaire tailwind)
echo "--- 1. Vérification dépendances ---\n";
run('composer install --optimize-autoloader'); 

// 2. Construction de Tailwind (C'est ça qui manque !)
echo "--- 2. Construction de Tailwind CSS ---\n";
// On tente de télécharger le binaire si absent et de construire
run('php bin/console tailwind:build --minify');

// 3. Compilation des assets (AssetMapper)
echo "--- 3. Compilation des Assets ---\n";
run('php bin/console asset-map:compile');

// 4. Cache
echo "--- 4. Nettoyage Cache ---\n";
run('php bin/console cache:clear');

echo "</pre><h1>Terminé !</h1>";
