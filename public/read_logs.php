<?php
// public/read_logs.php
header('Content-Type: text/plain');
$logFile = __DIR__ . '/../var/log/prod.log';

if (file_exists($logFile)) {
    echo "--- DERNIÈRES LIGNES DE PROD.LOG ---\n";
    // Read the last 100 lines
    $content = file($logFile);
    $lastLines = array_slice($content, -100);
    echo implode("", $lastLines);
} else {
    echo "Fichier de log introuvable : $logFile\n";
    // Check if there is a dev.log or other logs
    $logDir = __DIR__ . '/../var/log';
    if (is_dir($logDir)) {
        echo "\nFichiers dans le dossier log :\n";
        print_r(scandir($logDir));
    }
}
