<?php
/**
 * composer_fix.php - Ultra-minimal composer executor
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(600);
ini_set('memory_limit', '512M');

echo "<h1>🚀 Tentative de Composer Install</h1>";
$rootDir = dirname(__DIR__);
chdir($rootDir);

echo "<strong>Exécution de : composer install...</strong><br>";
echo "<pre>";
passthru('composer install --optimize-autoloader --no-dev --no-interaction 2>&1', $return_var);
echo "</pre>";

if ($return_var === 0) {
    echo "<h2 style='color:green'>✅ Succès !</h2>";
    echo "<p>Le dossier 'vendor' a été créé. Vous pouvez maintenant essayer <a href='recover.php?key=MonSecret'>recover.php</a>.</p>";
} else {
    echo "<h2 style='color:red'>❌ Échec (Code $return_var)</h2>";
    echo "<p>Le serveur AlwaysData refuse peut-être de lancer composer via PHP.</p>";
    echo "<p><strong>Solution de secours :</strong> Envoyez le dossier 'vendor' de votre ordinateur vers le serveur via FTP.</p>";
}
?>
