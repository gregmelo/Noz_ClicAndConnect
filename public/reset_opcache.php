<?php
// public/reset_opcache.php
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "<h1>OPCache réinitialisé !</h1>";
} else {
    echo "<h1>OPCache n'est pas activé ou accessible.</h1>";
}
echo "<p>Réessayez de visiter votre site maintenant.</p>";
echo "<p><a href='/'>Retour à l'accueil</a></p>";
