<?php
// public/check_env.php
require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    echo "<h1>Diagnostic Environnement</h1>";
    echo "APP_ENV: <strong>" . ($context['APP_ENV'] ?? 'non défini') . "</strong><br>";
    echo "APP_DEBUG: <strong>" . ($context['APP_DEBUG'] ? 'vrai' : 'faux') . "</strong><br>";
    echo "Méthode de détection: <strong>" . (getenv('APP_ENV') ? 'Variable Système' : 'Fichier .env') . "</strong><br>";
    echo "<hr>";
    
    $localEnvPhp = dirname(__DIR__) . '/.env.local.php';
    echo ".env.local.php présent : <strong>" . (file_exists($localEnvPhp) ? 'OUI' : 'NON') . "</strong><br>";
    
    // Diagnostic des Bundles et Cache
    $kernel = new \App\Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
    echo "Environnement Kernel: <strong>" . $kernel->getEnvironment() . "</strong><br>";
    echo "Debug Kernel: <strong>" . ($kernel->isDebug() ? 'OUI' : 'NON') . "</strong><br>";
    echo "Dossier Cache: <strong>" . $kernel->getCacheDir() . "</strong><br>";
    echo "<hr>";
    
    $kernel->boot();
    echo "<h3>Dossiers de cache présents sur le serveur :</h3><ul>";
    $cacheBase = dirname(__DIR__) . '/var/cache';
    if (is_dir($cacheBase)) {
        foreach (array_diff(scandir($cacheBase), ['.', '..']) as $d) {
            echo "<li>$d</li>";
        }
    } else {
        echo "<li>Aucun dossier var/cache trouvé !</li>";
    }
    echo "</ul>";
    
    echo "<h3>Bundles chargés :</h3><ul>";
    foreach (array_keys($kernel->getBundles()) as $bundleName) {
        $color = str_contains($bundleName, 'WebProfiler') || str_contains($bundleName, 'Debug') ? 'red' : 'green';
        echo "<li style='color: $color'>$bundleName</li>";
    }
    echo "</ul>";
    
    echo "<hr>";
    echo "Si vous voyez <strong>WebProfilerBundle</strong> en <span style='color:red'>rouge</span> ci-dessus, c'est que la barre Symfony est activée.";
    exit;
};
