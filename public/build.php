<?php
// public/build.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Optimisation Production (Build manuel)</h1><pre>";

$rootDir = dirname(__DIR__);

// 1. Forcer la génération du fichier .env.local.php (ce que fait 'composer dump-env prod')
echo "1. Génération de .env.local.php...\n";
$envFile = $rootDir . '/.env';
if (file_exists($envFile)) {
    $content = file_get_contents($envFile);
    // On extrait les variables ou on en crée une propre
    $user = '443264';
    $pass = '#Sf!6$AG$eo%$U34N8Tp';
    $host = 'mysql-nozclicandcollect.alwaysdata.net';
    $db = 'nozclicandcollect_db';
    $dsn = "mysql://" . urlencode($user) . ":" . urlencode($pass) . "@" . $host . ":3306/" . $db;

    $phpData = "<?php\n\nreturn array (\n  'APP_ENV' => 'prod',\n  'APP_DEBUG' => '0',\n  'APP_SECRET' => 'abcf30ee722faee8ebc8f494223a00b5',\n  'DEFAULT_URI' => 'https://nozclicandcollect.alwaysdata.net',\n  'DATABASE_URL' => '" . $dsn . "',\n  'MESSENGER_TRANSPORT_DSN' => 'doctrine://default?auto_setup=0',\n  'MAILER_DSN' => 'gmail://nozclicandcollect@gmail.com:gounsyvpgkkzwryl@default',\n);\n";
    
    file_put_contents($rootDir . '/.env.local.php', $phpData);
    echo "✅ .env.local.php créé avec les accès SQL Alwaysdata.\n";
    echo "✅ .env.local.php créé (Mode PROD forcé).\n";
} else {
    echo "❌ Fichier .env introuvable.\n";
}

// 2. Nettoyage TOTAL du cache dev
echo "\n2. Nettoyage des caches résiduels...\n";
$cacheDir = $rootDir . '/var/cache';
if (is_dir($cacheDir)) {
    $folders = array_diff(scandir($cacheDir), ['.', '..']);
    foreach ($folders as $f) {
        system("rm -rf " . escapeshellarg($cacheDir . '/' . $f));
        echo "   Suppression de $f...\n";
    }
}

// 3. Instruction finale
echo "\n--- TERMINÉ ---\n";
echo "Le mode production est maintenant VERROUILLÉ par le fichier .env.local.php.\n";
echo "Veuillez maintenant :\n";
echo "1. Supprimer le fichier public/build.php\n";
echo "2. Faire un Ctrl+F5 sur votre site.\n";
echo "</pre>";
