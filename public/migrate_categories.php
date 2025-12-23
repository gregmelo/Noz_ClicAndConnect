<?php
/**
 * Script de migration pour le système de catégories
 * À exécuter une seule fois sur le serveur Alwaysdata.
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Migration : Système de Catégories</h1><pre>";

// Identifiants Alwaysdata (extraits de build.php)
$user = '443264';
$pass = '#Sf!6$AG$eo%$U34N8Tp';
$host = 'mysql-nozclicandcollect.alwaysdata.net';
$db = 'nozclicandcollect_db';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Créer la table category
    echo "1. Création de la table 'category'...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS category (
        id INT AUTO_INCREMENT NOT NULL, 
        name VARCHAR(100) NOT NULL, 
        PRIMARY KEY(id)
    ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
    echo "✅ Table 'category' prête.\n";

    // 2. Ajouter la colonne category_id dans product
    echo "2. Vérification de la colonne 'category_id' dans 'product'...\n";
    $checkColumn = $pdo->query("SHOW COLUMNS FROM product LIKE 'category_id'");
    if (!$checkColumn->fetch()) {
        $pdo->exec("ALTER TABLE product ADD category_id INT DEFAULT NULL");
        $pdo->exec("ALTER TABLE product ADD CONSTRAINT FK_D34A04AD12469DE2 FOREIGN KEY (category_id) REFERENCES category (id)");
        $pdo->exec("CREATE INDEX IDX_D34A04AD12469DE2 ON product (category_id)");
        echo "✅ Colonne 'category_id' et index ajoutés.\n";
    } else {
        echo "ℹ️ La colonne 'category_id' existe déjà.\n";
    }

    echo "\n--- TERMINÉ ---\n";
    echo "Vous pouvez maintenant supprimer ce fichier : <b>public/migrate_categories.php</b>";

} catch (Exception $e) {
    echo "❌ Erreur : " . $e->getMessage();
}
echo "</pre>";
