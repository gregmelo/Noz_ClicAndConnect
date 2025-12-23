<?php
/**
 * Script de migration pour l'historisation des prix
 * À exécuter une seule fois sur le serveur Alwaysdata.
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Migration : Historisation des Prix</h1><pre>";

// Identifiants Alwaysdata (extraits de build.php)
$user = '443264';
$pass = '#Sf!6$AG$eo%$U34N8Tp';
$host = 'mysql-nozclicandcollect.alwaysdata.net';
$db = 'nozclicandcollect_db';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Ajouter la colonne si elle n'existe pas
    echo "1. Vérification de la colonne 'price' dans 'reservation_item'...\n";
    $checkColumn = $pdo->query("SHOW COLUMNS FROM reservation_item LIKE 'price'");
    if (!$checkColumn->fetch()) {
        $pdo->exec("ALTER TABLE reservation_item ADD price DECIMAL(10, 2) NOT NULL DEFAULT 0");
        echo "✅ Colonne 'price' ajoutée.\n";
    } else {
        echo "ℹ️ La colonne 'price' existe déjà.\n";
    }

    // 2. Remplir les prix vides à partir de la table product
    echo "2. Remplissage des prix historiques (via les prix actuels des produits)...\n";
    $sql = "UPDATE reservation_item ri 
            JOIN product p ON ri.product_id = p.id 
            SET ri.price = p.price 
            WHERE ri.price = 0";
    $count = $pdo->exec($sql);
    echo "✅ $count lignes mises à jour avec le prix actuel des produits.\n";

    echo "\n--- TERMINÉ ---\n";
    echo "Vous pouvez maintenant supprimer ce fichier : <b>public/update_prices.php</b>";

} catch (Exception $e) {
    echo "❌ Erreur : " . $e->getMessage();
}
echo "</pre>";
