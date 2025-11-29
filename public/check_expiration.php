<?php
// check_expiration.php - Script pour forcer la vérification des expirations
// À placer dans /public et appeler via le navigateur

$secret = 'MonSecret';
if (($_GET['key'] ?? '') !== $secret) die('Accès refusé');

echo "<h1>Vérification des Expirations</h1><pre>";
chdir('..');

// Lance la commande Symfony qui gère les expirations et les strikes
passthru('php bin/console app:check-expired-reservations 2>&1');

echo "</pre><h1>Terminé !</h1>";
echo "<p>Retournez sur le Dashboard pour voir si le compteur a changé et si le client a pris un strike.</p>";
